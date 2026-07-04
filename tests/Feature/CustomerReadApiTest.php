<?php

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Models\Province;
use App\Models\User;
use App\Notifications\AppraisalRequestCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('customer', 'web');
});

it('protects customer read endpoints with sanctum', function (): void {
    $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    $this->getJson('/api/v1/appraisals')->assertUnauthorized();
    $this->getJson('/api/v1/profile')->assertUnauthorized();
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
});

it('returns a dashboard containing only the authenticated customer data', function (): void {
    [$customer, $token] = mobileReadCustomer();
    [$other] = mobileReadCustomer();

    $own = mobileReadAppraisal($customer, [
        'request_number' => 'REQ-OWN-DASHBOARD',
        'status' => AppraisalStatusEnum::DocsIncomplete,
    ]);
    mobileReadAppraisal($other, [
        'request_number' => 'REQ-OTHER-DASHBOARD',
        'status' => AppraisalStatusEnum::Completed,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/dashboard')
        ->assertOk()
        ->assertJsonPath('data.stats.total_requests', 1)
        ->assertJsonPath('data.stats.need_revision', 1)
        ->assertJsonPath('data.featured_request.id', $own->id)
        ->assertJsonCount(1, 'data.recent_requests')
        ->assertJsonMissing(['request_number' => 'REQ-OTHER-DASHBOARD'])
        ->assertJsonMissingPath('data.featured_request.detail_url')
        ->assertJsonPath('data.featured_request.status.action_key', 'submit_revision');
});

it('filters and paginates appraisal summaries without leaking another customer records', function (): void {
    [$customer, $token] = mobileReadCustomer();
    [$other] = mobileReadCustomer();

    mobileReadAppraisal($customer, [
        'request_number' => 'REQ-FILTER-MATCH',
        'status' => AppraisalStatusEnum::Submitted,
    ]);
    mobileReadAppraisal($customer, [
        'request_number' => 'REQ-FILTER-COMPLETED',
        'status' => AppraisalStatusEnum::Completed,
    ]);
    mobileReadAppraisal($other, [
        'request_number' => 'REQ-FILTER-OTHER',
        'status' => AppraisalStatusEnum::Submitted,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/appraisals?status=submitted&q=MATCH&per_page=10')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.request_number', 'REQ-FILTER-MATCH')
        ->assertJsonPath('stats.total', 2)
        ->assertJsonPath('stats.by_status.submitted', 1)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonMissing(['request_number' => 'REQ-FILTER-OTHER']);
});

it('validates appraisal list filters', function (): void {
    [, $token] = mobileReadCustomer();

    $this->withToken($token)
        ->getJson('/api/v1/appraisals?status=not-a-real-status&per_page=99')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status', 'per_page']);
});

it('exposes backend enum and initial location options', function (): void {
    [, $token] = mobileReadCustomer();
    Province::query()->create(['id' => '31', 'name' => 'DKI Jakarta']);

    $this->withToken($token)
        ->getJson('/api/v1/appraisals/options')
        ->assertOk()
        ->assertJsonPath('data.provinces.0.id', '31')
        ->assertJsonPath('data.statuses.0.value', 'draft')
        ->assertJsonFragment(['value' => 'tanah_bangunan'])
        ->assertJsonFragment(['value' => 'jual_beli']);
});

it('returns owned appraisal detail and hides another customer appraisal', function (): void {
    [$customer, $token] = mobileReadCustomer();
    [$other] = mobileReadCustomer();
    $own = mobileReadAppraisal($customer, ['request_number' => 'REQ-DETAIL-OWN']);
    $foreign = mobileReadAppraisal($other, ['request_number' => 'REQ-DETAIL-FOREIGN']);

    $this->withToken($token)
        ->getJson("/api/v1/appraisals/{$own->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $own->id)
        ->assertJsonPath('data.assets.0.address', 'Jl. Mobile API No. 1')
        ->assertJsonMissingPath('data.tracking_page_url')
        ->assertJsonMissingPath('data.report_pdf_path');

    $this->withToken($token)
        ->getJson("/api/v1/appraisals/{$foreign->id}")
        ->assertNotFound();
});

it('returns a customer-safe tracking timeline and enforces ownership', function (): void {
    [$customer, $token] = mobileReadCustomer();
    [$other] = mobileReadCustomer();
    $own = mobileReadAppraisal($customer, ['request_number' => 'REQ-TRACK-OWN']);
    $foreign = mobileReadAppraisal($other, ['request_number' => 'REQ-TRACK-FOREIGN']);

    $this->withToken($token)
        ->getJson("/api/v1/appraisals/{$own->id}/tracking")
        ->assertOk()
        ->assertJsonPath('data.request.id', $own->id)
        ->assertJsonPath('data.timeline.0.key', 'request_submitted')
        ->assertJsonMissingPath('data.tracking_page_url')
        ->assertJsonMissingPath('data.tracking_context.back_url');

    $this->withToken($token)
        ->getJson("/api/v1/appraisals/{$foreign->id}/tracking")
        ->assertNotFound();
});

it('returns profile and billing readiness for the authenticated customer', function (): void {
    [$customer, $token] = mobileReadCustomer([
        'phone_number' => '081234567890',
        'billing_recipient_name' => 'Finance DigiPro',
        'billing_address_detail' => 'Jl. Billing No. 10',
        'billing_email' => 'billing@example.com',
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/profile')
        ->assertOk()
        ->assertJsonPath('data.id', $customer->id)
        ->assertJsonPath('data.billing.email', 'billing@example.com')
        ->assertJsonPath('data.profile_complete', true);
});

it('paginates only the authenticated customer notifications without exposing web urls', function (): void {
    [$customer, $token] = mobileReadCustomer();
    [$other] = mobileReadCustomer();
    $own = mobileReadAppraisal($customer, ['request_number' => 'REQ-NOTIFICATION-OWN']);
    $foreign = mobileReadAppraisal($other, ['request_number' => 'REQ-NOTIFICATION-OTHER']);

    $customer->notify(new AppraisalRequestCreated($own->id, $own->request_number));
    $other->notify(new AppraisalRequestCreated($foreign->id, $foreign->request_number));

    $this->withToken($token)
        ->getJson('/api/v1/notifications?per_page=20')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('data.0.action.resource_id', $own->id)
        ->assertJsonPath('data.0.action.key', 'view_appraisal')
        ->assertJsonMissingPath('data.0.url')
        ->assertJsonMissing(['appraisal_id' => $foreign->id]);
});

function mobileReadCustomer(array $attributes = []): array
{
    $user = User::factory()->create($attributes);
    $user->assignRole('customer');

    return [
        $user,
        $user->createToken('mobile-read-test', ['mobile:customer'])->plainTextToken,
    ];
}

function mobileReadAppraisal(User $user, array $overrides = []): AppraisalRequest
{
    $record = AppraisalRequest::query()->create(array_merge([
        'user_id' => $user->id,
        'request_number' => 'REQ-'.Str::upper(Str::random(10)),
        'purpose' => 'jual_beli',
        'valuation_objective' => 'kajian_nilai_pasar_dalam_bentuk_range',
        'client_name' => 'Customer Mobile',
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
        'report_type' => 'terinci',
        'report_format' => 'digital',
    ], $overrides));

    AppraisalAsset::query()->create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah_bangunan',
        'land_area' => 120,
        'building_area' => 80,
        'address' => 'Jl. Mobile API No. 1',
    ]);

    return $record;
}
