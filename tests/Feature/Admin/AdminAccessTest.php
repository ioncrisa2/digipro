<?php

use App\Enums\AppraisalStatusEnum;
use App\Enums\PurposeEnum;
use App\Enums\ContractStatusEnum;
use Carbon\Carbon;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetFile;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestFile;
use App\Models\AppraisalRequestRevisionBatch;
use App\Models\AppraisalRequestRevisionItem;
use App\Models\AppraisalUserConsent;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\BuildingEconomicLife;
use App\Models\ConsentDocument;
use App\Models\ConstructionCostIndex;
use App\Models\ContactMessage;
use App\Models\CostElement;
use App\Models\Faq;
use App\Models\Feature;
use App\Models\FloorIndex;
use App\Models\GuidelineSet;
use App\Models\OfficeBankAccount;
use App\Models\Payment;
use App\Models\PrivacyPolicy;
use App\Models\Province;
use App\Models\Regency;
use App\Models\MappiRcnStandard;
use App\Models\Tag;
use App\Models\Testimonial;
use App\Models\TermsDocument;
use App\Models\User;
use App\Models\ValuationSetting;
use App\Models\District;
use App\Models\Village;
use App\Support\SystemNavigation;
use App\Support\AdminWorkspaceAccessSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Role::findOrCreate('customer', 'web');

    foreach ([
        'view_any_role',
        'view_role',
        'create_role',
        'update_role',
        'delete_role',
        'delete_any_role',
        'view_article',
        'create_article',
        'update_article',
    ] as $permissionName) {
        Permission::findOrCreate($permissionName, 'web');
    }

    AdminWorkspaceAccessSynchronizer::sync();
});

function createAdminUser(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('admin');

    return $user;
}

function createSuperAdminUser(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $user->assignRole('super_admin');

    return $user;
}

function createReviewerUser(bool $withSystemMenus = true): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    if ($withSystemMenus) {
        $user->assignRole('Reviewer');
    } else {
        $user->assignRole('customer');
    }

    return $user;
}

it('allows admin users to access the vue admin dashboard', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard'));
});

it('shares super admin widgets on the admin dashboard for super admin users', function () {
    $superAdmin = createSuperAdminUser();

    $this
        ->actingAs($superAdmin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Dashboard')
            ->where('isSuperAdmin', true)
            ->has('superAdminWidgets.system_health', 6)
            ->has('superAdminWidgets.role_summary', 6)
            ->has('superAdminWidgets.reference_completeness.items', 6)
            ->has('superAdminWidgets.exception_queue', 5)
            ->has('superAdminWidgets.permission_overview.summary')
            ->has('superAdminWidgets.sensitive_changes')
        );
});

it('redirects reviewer users away from the admin root back to reviewer workspace', function () {
    $reviewer = createReviewerUser();

    $this
        ->actingAs($reviewer)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('reviewer.dashboard'));
});

it('blocks users without admin workspace permission from the admin root', function () {
    $reviewer = createReviewerUser(withSystemMenus: false);

    $this
        ->actingAs($reviewer)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

it('allows reviewer users to access reference guideline pages in reviewer workspace', function () {
    $reviewer = createReviewerUser();

    $this
        ->actingAs($reviewer)
        ->get(route('reviewer.ref-guidelines.guideline-sets.index'))
        ->assertOk();
});

it('allows reviewer users to access master data location pages in reviewer workspace', function () {
    $reviewer = createReviewerUser();

    $this
        ->actingAs($reviewer)
        ->get(route('reviewer.master-data.provinces.index'))
        ->assertOk();
});

it('redirects reviewer users away from admin-prefixed shared routes', function () {
    $reviewer = createReviewerUser();

    $this
        ->actingAs($reviewer)
        ->get(route('admin.master-data.provinces.index'))
        ->assertRedirect(route('reviewer.dashboard'));
});

it('shares filtered system navigation for reviewer and admin layouts', function () {
    $reviewer = createReviewerUser();

    $this
        ->actingAs($reviewer)
        ->get(route('reviewer.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.system_section_permissions', [
                SystemNavigation::ACCESS_REVIEWER_DASHBOARD,
                SystemNavigation::MANAGE_REVIEWER_REVIEWS,
                SystemNavigation::MANAGE_REVIEWER_COMPARABLES,
                SystemNavigation::MANAGE_ADMIN_MASTER_DATA,
                SystemNavigation::MANAGE_ADMIN_REF_GUIDELINES,
            ])
            ->has('navigation.reviewer_nav', 5)
            ->where('navigation.reviewer_nav.0.key', 'reviewer.dashboard')
            ->where('navigation.reviewer_nav.3.key', 'reviewer.master-data')
            ->where('navigation.reviewer_nav.4.key', 'reviewer.ref-guidelines')
            ->has('navigation.admin_nav', 2)
            ->where('navigation.admin_nav.0.key', 'admin.master-data')
            ->where('navigation.admin_nav.1.key', 'admin.ref-guidelines'));
});

it('redirects admin users away from the customer dashboard', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});

it('redirects admin users away from customer appraisal creation flow', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->get(route('appraisal.create'))
        ->assertRedirect(route('admin.dashboard'));
});

it('allows admin users to access the shared profile page with admin layout context', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Index')
            ->where('layoutContext', 'admin'));
});

it('renders appraisal request detail in the vue admin workspace', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ADMIN-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.appraisal-requests.show', $record))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AppraisalRequests/Show')
            ->where('record.request_number', 'REQ-ADMIN-001'));
});

it('renders the appraisal request index for admin users', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ADMIN-INDEX-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::WaitingOffer,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.appraisal-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AppraisalRequests/Index')
            ->where('records.meta.total', 1));
});

it('renders the admin payments index in the vue workspace', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-PAYMENT-INDEX-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'requested_at' => now(),
    ]);

    Payment::create([
        'appraisal_request_id' => $record->id,
        'amount' => 15000000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'MID-ADMIN-LIST-001',
        'status' => 'pending',
        'proof_type' => 'gateway_id',
        'metadata' => [
            'invoice_number' => 'INV-2026-90001',
            'gateway_details' => [
                'payment_type' => 'bank_transfer',
                'va_numbers' => [['bank' => 'bca', 'va_number' => '1234567890']],
            ],
        ],
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.finance.payments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Payments/Index')
            ->where('records.data.0.invoice_number', 'INV-2026-90001')
            ->where('records.data.0.external_payment_id', 'MID-ADMIN-LIST-001'));
});

it('renders the admin payment detail in the vue workspace', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-PAYMENT-SHOW-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'requested_at' => now(),
    ]);

    $payment = Payment::create([
        'appraisal_request_id' => $record->id,
        'amount' => 17500000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'MID-ADMIN-SHOW-001',
        'status' => 'paid',
        'proof_type' => 'gateway_id',
        'paid_at' => now(),
        'metadata' => [
            'invoice_number' => 'INV-2026-90002',
            'gateway_details' => [
                'payment_type' => 'qris',
                'transaction_id' => 'TXN-001',
            ],
        ],
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.finance.payments.show', $payment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Payments/Show')
            ->where('record.invoice_number', 'INV-2026-90002')
            ->where('record.external_payment_id', 'MID-ADMIN-SHOW-001')
            ->where('record.edit_url', null));
});

it('renders the admin office bank accounts index in the vue workspace', function () {
    $admin = createAdminUser();

    OfficeBankAccount::create([
        'bank_name' => 'Bank Digi',
        'account_number' => '1234567890',
        'account_holder' => 'PT Digi Pro',
        'currency' => 'IDR',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.finance.office-bank-accounts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/OfficeBankAccounts/Index')
            ->where('records.0.bank_name', 'Bank Digi')
            ->where('summary.active', 1));
});

it('renders the admin office bank account create page in the vue workspace', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->get(route('admin.finance.office-bank-accounts.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/OfficeBankAccounts/Form')
            ->where('mode', 'create')
            ->where('record.currency', 'IDR'));
});

it('stores an office bank account from the vue admin workspace', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->post(route('admin.finance.office-bank-accounts.store'), [
            'bank_name' => 'Bank Baru',
            'account_number' => '9876543210',
            'account_holder' => 'PT Digi Baru',
            'branch' => 'Jakarta',
            'currency' => 'idr',
            'notes' => 'rekening operasional',
            'is_active' => true,
            'sort_order' => 2,
        ])
        ->assertRedirect(route('admin.finance.office-bank-accounts.index'));

    $record = OfficeBankAccount::query()->where('account_number', '9876543210')->first();

    expect($record)->not->toBeNull();
    expect($record->currency)->toBe('IDR');
});

it('renders the admin office bank account edit page in the vue workspace', function () {
    $admin = createAdminUser();
    $account = OfficeBankAccount::create([
        'bank_name' => 'Bank Edit',
        'account_number' => '444333222',
        'account_holder' => 'PT Digi Edit',
        'currency' => 'IDR',
        'is_active' => true,
        'sort_order' => 3,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.finance.office-bank-accounts.edit', $account))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/OfficeBankAccounts/Form')
            ->where('mode', 'edit')
            ->where('record.account_number', '444333222'));
});

it('updates an office bank account from the vue admin workspace', function () {
    $admin = createAdminUser();
    $account = OfficeBankAccount::create([
        'bank_name' => 'Bank Update',
        'account_number' => '555444333',
        'account_holder' => 'PT Digi Update',
        'currency' => 'IDR',
        'is_active' => true,
        'sort_order' => 4,
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.finance.office-bank-accounts.update', $account), [
            'bank_name' => 'Bank Update Final',
            'account_number' => '555444333',
            'account_holder' => 'PT Digi Update Final',
            'branch' => 'Bandung',
            'currency' => 'usd',
            'notes' => 'updated',
            'is_active' => false,
            'sort_order' => 8,
        ])
        ->assertRedirect(route('admin.finance.office-bank-accounts.index'));

    $account->refresh();

    expect($account->bank_name)->toBe('Bank Update Final');
    expect($account->currency)->toBe('USD');
    expect($account->is_active)->toBeFalse();
    expect($account->sort_order)->toBe(8);
});

it('deletes an office bank account from the vue admin workspace', function () {
    $admin = createAdminUser();
    $account = OfficeBankAccount::create([
        'bank_name' => 'Bank Delete',
        'account_number' => '111222333',
        'account_holder' => 'PT Digi Delete',
        'currency' => 'IDR',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.finance.office-bank-accounts.destroy', $account))
        ->assertRedirect(route('admin.finance.office-bank-accounts.index'));

    expect(OfficeBankAccount::find($account->id))->toBeNull();
});

it('renders the admin payment edit page in the vue workspace', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $requestRecord = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-PAYMENT-EDIT-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'requested_at' => now(),
    ]);

    $payment = Payment::create([
        'appraisal_request_id' => $requestRecord->id,
        'amount' => 18000000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'MID-ADMIN-EDIT-001',
        'status' => 'pending',
        'proof_type' => 'gateway_id',
        'metadata' => [
            'invoice_number' => 'INV-2026-90003',
        ],
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.finance.payments.edit', $payment))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Payments/Edit')
            ->where('record.invoice_number', 'INV-2026-90003')
            ->where('record.external_payment_id', 'MID-ADMIN-EDIT-001'));
});

it('blocks editing a paid payment from the vue admin workspace', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create(['email_verified_at' => now()]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-PAID-LOCKED',
        'status' => AppraisalStatusEnum::ContractSigned->value,
        'company_name' => 'PT Uji',
        'contact_person' => 'Ari',
        'phone_number' => '081111111111',
        'email' => 'request@example.com',
        'client_type' => 'company',
        'client_name' => 'PT Uji',
        'fee_amount' => 1000000,
        'payment_method' => 'gateway',
        'payment_status' => 'paid',
        'contract_status' => ContractStatusEnum::ContractSigned->value,
        'report_type' => 'terinci',
        'purpose' => PurposeEnum::PenjaminanUtang->value,
        'property_type' => 'ruko',
        'bank_name' => 'Bank Test',
    ]);

    $payment = Payment::query()->create([
        'appraisal_request_id' => $record->id,
        'amount' => 1000000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'MID-PAID-LOCKED-001',
        'status' => 'paid',
        'paid_at' => now(),
        'metadata' => ['invoice_number' => 'INV-2026-PAID-LOCKED'],
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.finance.payments.edit', $payment))
        ->assertForbidden();
});

it('updates a payment from the vue admin workspace', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $requestRecord = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-PAYMENT-UPDATE-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'requested_at' => now(),
    ]);

    $payment = Payment::create([
        'appraisal_request_id' => $requestRecord->id,
        'amount' => 19000000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'MID-ADMIN-UPDATE-001',
        'status' => 'pending',
        'proof_type' => 'gateway_id',
        'metadata' => [
            'invoice_number' => 'INV-2026-90004',
        ],
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.finance.payments.update', $payment), [
            'amount' => 21000000,
            'status' => 'paid',
            'gateway' => 'midtrans',
            'external_payment_id' => 'MID-ADMIN-UPDATED',
            'paid_at' => '2026-03-20 10:30:00',
            'metadata_json' => json_encode([
                'invoice_number' => 'INV-2026-90004A',
                'gateway_details' => [
                    'payment_type' => 'bank_transfer',
                    'va_numbers' => [['bank' => 'bni', 'va_number' => '99887766']],
                ],
            ]),
        ])
        ->assertRedirect(route('admin.finance.payments.show', $payment));

    $payment->refresh();

    expect($payment->amount)->toBe(21000000);
    expect($payment->status)->toBe('paid');
    expect($payment->external_payment_id)->toBe('MID-ADMIN-UPDATED');
    expect(data_get($payment->metadata, 'invoice_number'))->toBe('INV-2026-90004A');
});

it('renders the admin articles index in the vue workspace', function () {
    $admin = createAdminUser();

    $category = ArticleCategory::create([
        'name' => 'Insight',
        'slug' => 'insight',
        'is_active' => true,
        'show_in_nav' => true,
    ]);

    $tag = Tag::create([
        'name' => 'Valuation',
        'slug' => 'valuation',
        'is_active' => true,
    ]);

    $article = Article::create([
        'title' => 'Analisis Pasar Properti',
        'slug' => 'analisis-pasar-properti',
        'excerpt' => 'Ringkasan analisis',
        'content_html' => '<p>Konten</p>',
        'category_id' => $category->id,
        'is_published' => true,
        'published_at' => now(),
    ]);
    $article->tags()->sync([$tag->id]);

    $this
        ->actingAs($admin)
        ->get(route('admin.content.articles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Articles/Index')
            ->where('records.data.0.title', 'Analisis Pasar Properti')
            ->where('summary.published', 1));
});

it('stores an article from the vue admin workspace', function () {
    Storage::fake('public');

    $admin = createAdminUser();
    $category = ArticleCategory::create([
        'name' => 'Berita',
        'slug' => 'berita',
        'is_active' => true,
        'show_in_nav' => true,
    ]);
    $tag = Tag::create([
        'name' => 'Properti',
        'slug' => 'properti',
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.content.articles.store'), [
            'title' => 'Artikel Baru',
            'slug' => 'artikel-baru',
            'excerpt' => 'Ringkasan artikel baru',
            'content_html' => '<p>Isi artikel baru</p>',
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
            'meta_title' => 'Meta Artikel Baru',
            'meta_description' => 'Meta description',
            'category_id' => $category->id,
            'tag_ids' => [$tag->id],
            'is_published' => true,
            'published_at' => '2026-03-20 09:00:00',
        ])
        ->assertRedirect(route('admin.content.articles.index'));

    $article = Article::query()->where('slug', 'artikel-baru')->first();

    expect($article)->not->toBeNull();
    expect($article->is_published)->toBeTrue();
    expect($article->category_id)->toBe($category->id);
    expect($article->tags()->count())->toBe(1);
    Storage::disk('public')->assertExists($article->cover_image_path);
});

it('uploads an inline article image from the vue admin workspace', function () {
    Storage::fake('public');

    $admin = createAdminUser();

    $response = $this
        ->actingAs($admin)
        ->post(route('admin.content.articles.images.store'), [
            'image' => UploadedFile::fake()->image('inline-article.jpg', 1200, 800),
            'alt' => 'Diagram appraisal workflow',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('alt', 'Diagram appraisal workflow');

    $path = $response->json('path');

    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('updates an article from the vue admin workspace', function () {
    $admin = createAdminUser();
    $categoryA = ArticleCategory::create([
        'name' => 'Kategori A',
        'slug' => 'kategori-a',
        'is_active' => true,
    ]);
    $categoryB = ArticleCategory::create([
        'name' => 'Kategori B',
        'slug' => 'kategori-b',
        'is_active' => true,
    ]);
    $tagA = Tag::create([
        'name' => 'Tag A',
        'slug' => 'tag-a',
        'is_active' => true,
    ]);
    $tagB = Tag::create([
        'name' => 'Tag B',
        'slug' => 'tag-b',
        'is_active' => true,
    ]);

    $article = Article::create([
        'title' => 'Artikel Lama',
        'slug' => 'artikel-lama',
        'content_html' => '<p>Lama</p>',
        'category_id' => $categoryA->id,
        'is_published' => false,
    ]);
    $article->tags()->sync([$tagA->id]);

    $this
        ->actingAs($admin)
        ->put(route('admin.content.articles.update', $article), [
            'title' => 'Artikel Final',
            'slug' => 'artikel-final',
            'excerpt' => 'Ringkasan final',
            'content_html' => '<p>Final</p>',
            'meta_title' => 'Meta Final',
            'meta_description' => 'Deskripsi final',
            'category_id' => $categoryB->id,
            'tag_ids' => [$tagB->id],
            'is_published' => true,
            'published_at' => '2026-03-20 11:00:00',
        ])
        ->assertRedirect(route('admin.content.articles.index'));

    $article->refresh();

    expect($article->title)->toBe('Artikel Final');
    expect($article->slug)->toBe('artikel-final');
    expect($article->category_id)->toBe($categoryB->id);
    expect($article->is_published)->toBeTrue();
    expect($article->tags()->pluck('tags.id')->all())->toBe([$tagB->id]);
});

it('deletes an article from the vue admin workspace', function () {
    $admin = createAdminUser();
    $article = Article::create([
        'title' => 'Artikel Hapus',
        'slug' => 'artikel-hapus',
        'content_html' => '<p>Hapus</p>',
        'is_published' => false,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.content.articles.destroy', $article))
        ->assertRedirect(route('admin.content.articles.index'));

    expect(Article::find($article->id))->toBeNull();
});

it('stores and updates an article category from the vue admin workspace', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->post(route('admin.content.categories.store'), [
            'name' => 'Kategori Baru',
            'slug' => 'kategori-baru',
            'description' => 'Deskripsi kategori',
            'sort_order' => 3,
            'is_active' => true,
            'show_in_nav' => true,
        ])
        ->assertRedirect(route('admin.content.categories.index'));

    $category = ArticleCategory::query()->where('slug', 'kategori-baru')->first();
    expect($category)->not->toBeNull();

    $this
        ->actingAs($admin)
        ->put(route('admin.content.categories.update', $category), [
            'name' => 'Kategori Final',
            'slug' => 'kategori-final',
            'description' => 'Final',
            'sort_order' => 5,
            'is_active' => false,
            'show_in_nav' => false,
        ])
        ->assertRedirect(route('admin.content.categories.index'));

    $category->refresh();

    expect($category->name)->toBe('Kategori Final');
    expect($category->is_active)->toBeFalse();
    expect($category->show_in_nav)->toBeFalse();
});

it('deletes an article category from the vue admin workspace', function () {
    $admin = createAdminUser();
    $category = ArticleCategory::create([
        'name' => 'Kategori Delete',
        'slug' => 'kategori-delete',
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.content.categories.destroy', $category))
        ->assertRedirect(route('admin.content.categories.index'));

    expect(ArticleCategory::find($category->id))->toBeNull();
});

it('reorders article categories from the vue admin workspace', function () {
    $admin = createAdminUser();
    $first = ArticleCategory::create([
        'name' => 'Kategori 1',
        'slug' => 'kategori-1',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $second = ArticleCategory::create([
        'name' => 'Kategori 2',
        'slug' => 'kategori-2',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.content.categories.reorder'), [
            'ids' => [$second->id, $first->id],
        ])
        ->assertRedirect(route('admin.content.categories.index'));

    expect($first->fresh()->sort_order)->toBe(2);
    expect($second->fresh()->sort_order)->toBe(1);
});

it('stores and updates a tag from the vue admin workspace', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->post(route('admin.content.tags.store'), [
            'name' => 'Tag Baru',
            'slug' => 'tag-baru',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.content.tags.index'));

    $tag = Tag::query()->where('slug', 'tag-baru')->first();
    expect($tag)->not->toBeNull();

    $this
        ->actingAs($admin)
        ->put(route('admin.content.tags.update', $tag), [
            'name' => 'Tag Final',
            'slug' => 'tag-final',
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.content.tags.index'));

    $tag->refresh();

    expect($tag->name)->toBe('Tag Final');
    expect($tag->is_active)->toBeFalse();
});

it('deletes a tag from the vue admin workspace', function () {
    $admin = createAdminUser();
    $tag = Tag::create([
        'name' => 'Tag Delete',
        'slug' => 'tag-delete',
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.content.tags.destroy', $tag))
        ->assertRedirect(route('admin.content.tags.index'));

    expect(Tag::find($tag->id))->toBeNull();
});

it('reorders tags from the vue admin workspace', function () {
    $admin = createAdminUser();
    $first = Tag::create([
        'name' => 'Tag 1',
        'slug' => 'tag-1',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $second = Tag::create([
        'name' => 'Tag 2',
        'slug' => 'tag-2',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.content.tags.reorder'), [
            'ids' => [$second->id, $first->id],
        ])
        ->assertRedirect(route('admin.content.tags.index'));

    expect($first->fresh()->sort_order)->toBe(2);
    expect($second->fresh()->sort_order)->toBe(1);
});

it('stores and updates an faq from the vue admin workspace', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->post(route('admin.content.legal.faqs.store'), [
            'question' => 'Apa itu DigiPro?',
            'answer' => 'Platform appraisal.',
            'sort_order' => 1,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.content.legal.faqs.index'));

    $faq = Faq::query()->where('question', 'Apa itu DigiPro?')->first();
    expect($faq)->not->toBeNull();

    $this
        ->actingAs($admin)
        ->put(route('admin.content.legal.faqs.update', $faq), [
            'question' => 'Apa itu DigiPro Final?',
            'answer' => 'Platform appraisal final.',
            'sort_order' => 2,
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.content.legal.faqs.index'));

    $faq->refresh();

    expect($faq->question)->toBe('Apa itu DigiPro Final?');
    expect($faq->is_active)->toBeFalse();
});

it('reorders faqs from the vue admin workspace', function () {
    $admin = createAdminUser();
    $first = Faq::create([
        'question' => 'FAQ 1',
        'answer' => 'Jawaban 1',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $second = Faq::create([
        'question' => 'FAQ 2',
        'answer' => 'Jawaban 2',
        'sort_order' => 2,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.content.legal.faqs.reorder'), [
            'ids' => [$second->id, $first->id],
        ])
        ->assertRedirect(route('admin.content.legal.faqs.index'));

    expect($first->fresh()->sort_order)->toBe(2);
    expect($second->fresh()->sort_order)->toBe(1);
});

it('stores and updates a feature from the vue admin workspace', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->post(route('admin.content.legal.features.store'), [
            'icon' => 'ShieldCheck',
            'title' => 'Aman',
            'description' => 'Proses aman',
            'sort_order' => 1,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.content.legal.features.index'));

    $feature = Feature::query()->where('title', 'Aman')->first();
    expect($feature)->not->toBeNull();

    $this
        ->actingAs($admin)
        ->put(route('admin.content.legal.features.update', $feature), [
            'icon' => 'Star',
            'title' => 'Aman Final',
            'description' => 'Proses aman final',
            'sort_order' => 3,
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.content.legal.features.index'));

    $feature->refresh();

    expect($feature->icon)->toBe('Star');
    expect($feature->is_active)->toBeFalse();
});

it('stores and updates a testimonial from the vue admin workspace', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->post(route('admin.content.legal.testimonials.store'), [
            'name' => 'Budi',
            'role' => 'Direktur',
            'quote' => 'Sangat membantu',
            'sort_order' => 1,
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.content.legal.testimonials.index'));

    $testimonial = Testimonial::query()->where('name', 'Budi')->first();
    expect($testimonial)->not->toBeNull();

    $this
        ->actingAs($admin)
        ->put(route('admin.content.legal.testimonials.update', $testimonial), [
            'name' => 'Budi Final',
            'role' => 'CEO',
            'quote' => 'Sangat membantu final',
            'sort_order' => 4,
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.content.legal.testimonials.index'));

    $testimonial->refresh();

    expect($testimonial->name)->toBe('Budi Final');
    expect($testimonial->is_active)->toBeFalse();
});

it('stores a terms document from the vue admin workspace', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->post(route('admin.content.legal.terms.store'), [
            'title' => 'Terms Baru',
            'company' => 'DigiPro',
            'version' => 'v2.0',
            'effective_since' => '2026-03-20',
            'content_html' => '<p>Terms</p>',
            'is_active' => true,
            'published_at' => '2026-03-20 10:00:00',
        ])
        ->assertRedirect(route('admin.content.legal.terms.index'));

    $terms = TermsDocument::query()->where('title', 'Terms Baru')->first();
    expect($terms)->not->toBeNull();
    expect($terms->is_active)->toBeTrue();
});

it('stores a privacy policy from the vue admin workspace', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->post(route('admin.content.legal.privacy.store'), [
            'title' => 'Privacy Baru',
            'company' => 'DigiPro',
            'version' => 'v2.0',
            'effective_since' => '2026-03-20',
            'content_html' => '<p>Privacy</p>',
            'is_active' => true,
            'published_at' => '2026-03-20 10:00:00',
        ])
        ->assertRedirect(route('admin.content.legal.privacy.index'));

    $policy = PrivacyPolicy::query()->where('title', 'Privacy Baru')->first();
    expect($policy)->not->toBeNull();
    expect($policy->is_active)->toBeTrue();
});

it('stores and publishes a consent document from the vue admin workspace', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->post(route('admin.content.legal.consent.store'), [
            'code' => 'appraisal_request_consent',
            'version' => '2026-03-20-v1',
            'title' => 'Consent Baru',
            'status' => 'draft',
            'checkbox_label' => 'Saya setuju',
            'sections_json' => json_encode([
                [
                    'heading' => 'Bagian 1',
                    'lead' => 'Lead',
                    'items' => ['Item 1', 'Item 2'],
                ],
            ]),
        ])
        ->assertRedirect(route('admin.content.legal.consent.index'));

    $document = ConsentDocument::query()->where('title', 'Consent Baru')->first();
    expect($document)->not->toBeNull();
    expect($document->status)->toBe('draft');

    $this
        ->actingAs($admin)
        ->post(route('admin.content.legal.consent.publish', $document))
        ->assertRedirect(route('admin.content.legal.consent.index'));

    $document->refresh();

    expect($document->status)->toBe('published');
    expect($document->hash)->not->toBe(str_repeat('0', 64));
});

it('renders the appraisal user consents pages in the vue admin workspace', function () {
    $admin = createAdminUser();
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $document = ConsentDocument::create([
        'code' => 'appraisal_request_consent',
        'version' => '2026-03-20-v2',
        'title' => 'Consent Audit',
        'sections' => [['heading' => 'A', 'lead' => null, 'items' => ['X']]],
        'checkbox_label' => 'Setuju',
        'hash' => str_repeat('1', 64),
        'status' => 'published',
        'published_at' => now(),
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    $consent = AppraisalUserConsent::create([
        'user_id' => $user->id,
        'consent_document_id' => $document->id,
        'code' => $document->code,
        'version' => $document->version,
        'hash' => $document->hash,
        'accepted_at' => now(),
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest Test',
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.content.legal.user-consents.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AppraisalUserConsents/Index')
            ->where('records.data.0.user_email', $user->email));

    $this
        ->actingAs($admin)
        ->get(route('admin.content.legal.user-consents.show', $consent))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AppraisalUserConsents/Show')
            ->where('record.code', 'appraisal_request_consent'));
});

it('renders the admin contact messages index in the vue admin workspace', function () {
    $admin = createAdminUser();

    ContactMessage::query()->create([
        'name' => 'Rina Support',
        'email' => 'rina@example.com',
        'subject' => 'Butuh bantuan appraisal',
        'message' => 'Halo admin, saya ingin bertanya tentang appraisal.',
        'status' => 'new',
        'source' => 'landing-contact',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.communications.contact-messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/ContactMessages/Index')
            ->where('summary.total', 1)
            ->where('records.meta.total', 1));
});

it('marks a contact message as read when opening the detail page', function () {
    $admin = createAdminUser();
    $message = ContactMessage::query()->create([
        'name' => 'Budi Contact',
        'email' => 'budi@example.com',
        'subject' => 'Follow up',
        'message' => 'Mohon follow up penawaran.',
        'status' => 'new',
        'source' => 'landing-contact',
        'ip_address' => '127.0.0.2',
        'user_agent' => 'Pest',
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.communications.contact-messages.show', $message))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/ContactMessages/Show')
            ->where('record.name', 'Budi Contact')
            ->where('record.status', 'in_progress'));

    $message->refresh();

    expect($message->read_at)->not->toBeNull();
    expect($message->status)->toBe('in_progress');
});

it('updates contact message workflow actions from the vue admin workspace', function () {
    $admin = createAdminUser();
    $message = ContactMessage::query()->create([
        'name' => 'Sari Contact',
        'email' => 'sari@example.com',
        'subject' => 'Status request',
        'message' => 'Tolong update status permohonan saya.',
        'status' => 'new',
        'source' => 'landing-contact',
        'ip_address' => '127.0.0.3',
        'user_agent' => 'Pest',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.communications.contact-messages.in-progress', $message))
        ->assertRedirect(route('admin.communications.contact-messages.show', $message));

    $message->refresh();
    expect($message->status)->toBe('in_progress');
    expect($message->read_at)->not->toBeNull();

    $this
        ->actingAs($admin)
        ->post(route('admin.communications.contact-messages.done', $message))
        ->assertRedirect(route('admin.communications.contact-messages.show', $message));

    $message->refresh();
    expect($message->status)->toBe('done');
    expect($message->handled_at)->not->toBeNull();
    expect($message->handled_by)->toBe($admin->id);

    $this
        ->actingAs($admin)
        ->post(route('admin.communications.contact-messages.archive', $message))
        ->assertRedirect(route('admin.communications.contact-messages.show', $message));

    $message->refresh();
    expect($message->status)->toBe('archived');

    $this
        ->actingAs($admin)
        ->delete(route('admin.communications.contact-messages.destroy', $message))
        ->assertRedirect(route('admin.communications.contact-messages.index'));

    expect(ContactMessage::query()->whereKey($message->id)->exists())->toBeFalse();
});

it('includes request files and grouped asset files in the admin request detail payload', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ADMIN-FILES-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    AppraisalRequestFile::create([
        'appraisal_request_id' => $record->id,
        'type' => 'contract_signed_pdf',
        'path' => 'contracts/test-contract.pdf',
        'original_name' => 'test-contract.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah_bangunan',
        'peruntukan' => 'rumah_tinggal',
        'address' => 'Jl. Admin Files No. 1',
        'land_area' => 100,
        'building_area' => 80,
    ]);

    AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'doc_pbb',
        'path' => 'assets/doc-pbb.pdf',
        'original_name' => 'doc-pbb.pdf',
        'mime' => 'application/pdf',
        'size' => 2048,
    ]);

    AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'photo_front',
        'path' => 'assets/photo-front.jpg',
        'original_name' => 'photo-front.jpg',
        'mime' => 'image/jpeg',
        'size' => 4096,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.appraisal-requests.show', $record))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AppraisalRequests/Show')
            ->where('requestFiles.0.type', 'contract_signed_pdf')
            ->where('assets.0.documents.0.type', 'doc_pbb')
            ->where('assets.0.photos.0.type', 'photo_front'));
});

it('includes negotiation summary and filter options in the admin request detail payload', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-ADMIN-NEGO-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::WaitingOffer,
        'requested_at' => now(),
    ]);

    $record->offerNegotiations()->create([
        'user_id' => $requester->id,
        'action' => 'counter_request',
        'round' => 1,
        'offered_fee' => 20000000,
        'expected_fee' => 18000000,
        'reason' => 'Mohon penyesuaian',
    ]);

    $record->offerNegotiations()->create([
        'user_id' => $admin->id,
        'action' => 'offer_revised',
        'round' => 1,
        'offered_fee' => 19000000,
        'reason' => 'Counter offer admin',
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.appraisal-requests.show', $record))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AppraisalRequests/Show')
            ->where('negotiationSummary.total', 2)
            ->where('negotiationSummary.counter_requests', 1)
            ->where('negotiationSummary.offers_sent', 1)
            ->where('negotiationActionOptions.0.value', 'offer_revised')
            ->where('negotiations.0.action_value', 'offer_revised'));
});

it('renders appraisal request detail as a verification workspace without admin mutation affordances', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-VERIFY-DETAIL-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_code' => 'AST-001',
        'asset_type' => 'tanah_bangunan',
        'address' => 'Jl. Verifikasi No. 1',
    ]);

    AppraisalRequestFile::create([
        'appraisal_request_id' => $record->id,
        'type' => 'npwp',
        'path' => 'appraisal-requests/request/npwp.pdf',
        'original_name' => 'npwp.pdf',
        'mime' => 'application/pdf',
        'size' => 123,
    ]);

    AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'doc_pbb',
        'path' => 'appraisal-requests/assets/pbb.pdf',
        'original_name' => 'pbb.pdf',
        'mime' => 'application/pdf',
        'size' => 456,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.appraisal-requests.show', $record))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AppraisalRequests/Show')
            ->missing('assetCreateUrl')
            ->missing('requestFileTypeOptions')
            ->missing('assetDocumentTypeOptions')
            ->missing('assetPhotoTypeOptions')
            ->where('assets.0.address', 'Jl. Verifikasi No. 1')
            ->missing('assets.0.edit_url')
            ->missing('assets.0.destroy_url')
            ->where('requestFiles.0.original_name', 'npwp.pdf')
            ->missing('requestFiles.0.can_delete')
            ->where('assets.0.documents.0.original_name', 'pbb.pdf')
            ->missing('assets.0.documents.0.destroy_url'));
});

it('exposes revision workspace data in the admin appraisal request detail', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-REVISION-WORKSPACE-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah_bangunan',
        'address' => 'Jl. Revisi Dokumen No. 2',
    ]);

    AppraisalRequestFile::create([
        'appraisal_request_id' => $record->id,
        'type' => 'npwp',
        'path' => 'appraisal-requests/request/revision-npwp.pdf',
        'original_name' => 'revision-npwp.pdf',
        'mime' => 'application/pdf',
        'size' => 500,
    ]);

    AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'doc_pbb',
        'path' => 'appraisal-requests/assets/revision-pbb.pdf',
        'original_name' => 'revision-pbb.pdf',
        'mime' => 'application/pdf',
        'size' => 600,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.appraisal-requests.show', $record))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AppraisalRequests/Show')
            ->where('revisionWorkspace.state.can_create', true)
            ->where('revisionWorkspace.target_options.0.item_type', 'request_file')
            ->where('revisionWorkspace.target_options.0.requested_file_type', 'npwp')
            ->where('revisionWorkspace.target_options.1.item_type', 'asset_document')
            ->where('revisionWorkspace.target_options.1.requested_file_type', 'doc_pbb'));
});

it('creates a revision batch from the admin appraisal workflow', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-REVISION-BATCH-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah_bangunan',
        'address' => 'Jl. Batch Revisi No. 7',
    ]);

    $requestFile = AppraisalRequestFile::create([
        'appraisal_request_id' => $record->id,
        'type' => 'npwp',
        'path' => 'appraisal-requests/request/npwp-batch.pdf',
        'original_name' => 'npwp-batch.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.revision-batches.store', $record), [
            'admin_note' => 'Mohon upload ulang dokumen yang tidak jelas dan lengkapi foto depan aset.',
            'items' => [
                [
                    'target_key' => "request_file:existing:{$requestFile->id}",
                    'issue_note' => 'NPWP buram, mohon unggah ulang file yang lebih jelas.',
                ],
                [
                    'target_key' => "asset_photo:missing:{$asset->id}:photo_front",
                    'issue_note' => 'Foto depan aset belum tersedia.',
                ],
            ],
        ])
        ->assertRedirect();

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::DocsIncomplete);

    $batch = AppraisalRequestRevisionBatch::query()
        ->where('appraisal_request_id', $record->id)
        ->first();

    expect($batch)->not->toBeNull();
    expect($batch->status)->toBe('open');
    expect($batch->admin_note)->toBe('Mohon upload ulang dokumen yang tidak jelas dan lengkapi foto depan aset.');

    $items = AppraisalRequestRevisionItem::query()
        ->where('revision_batch_id', $batch->id)
        ->orderBy('id')
        ->get();

    expect($items)->toHaveCount(2);
    expect($items[0]->item_type)->toBe('request_file');
    expect($items[0]->original_request_file_id)->toBe($requestFile->id);
    expect($items[0]->requested_file_type)->toBe('npwp');
    expect($items[1]->item_type)->toBe('asset_photo');
    expect($items[1]->appraisal_asset_id)->toBe($asset->id);
    expect($items[1]->original_asset_file_id)->toBeNull();
    expect($items[1]->requested_file_type)->toBe('photo_front');

    $requester->refresh();

    expect($requester->notifications()->count())->toBe(1);
    expect($requester->notifications()->first()->data['title'])->toBe('Revisi dokumen diperlukan');
    expect($requester->notifications()->first()->data['url'])->toContain("/permohonan-penilaian/{$record->id}/revisi-dokumen");
});

it('appends additional revision items to the active batch from the admin appraisal workflow', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-REVISION-BATCH-APPEND-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah_bangunan',
        'address' => 'Jl. Append Batch No. 9',
    ]);

    $requestFile = AppraisalRequestFile::create([
        'appraisal_request_id' => $record->id,
        'type' => 'npwp',
        'path' => 'appraisal-requests/request/npwp-append.pdf',
        'original_name' => 'npwp-append.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $assetFile = AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'doc_pbb',
        'path' => 'appraisal-requests/assets/doc-pbb-append.pdf',
        'original_name' => 'doc-pbb-append.pdf',
        'mime' => 'application/pdf',
        'size' => 1024,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.revision-batches.store', $record), [
            'items' => [
                [
                    'target_key' => "request_file:existing:{$requestFile->id}",
                    'issue_note' => 'Mohon unggah ulang NPWP yang lebih jelas.',
                ],
            ],
        ])
        ->assertRedirect();

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.revision-batches.store', $record), [
            'items' => [
                [
                    'target_key' => "asset_document:existing:{$assetFile->id}",
                    'issue_note' => 'PBB yang diunggah belum sesuai objek aset.',
                ],
            ],
        ])
        ->assertRedirect();

    $batches = AppraisalRequestRevisionBatch::query()
        ->where('appraisal_request_id', $record->id)
        ->get();

    expect($batches)->toHaveCount(1);

    $batch = $batches->first();

    expect($batch->items()->count())->toBe(2);
});

it('does not expose admin routes to mutate customer appraisal submissions', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-READONLY-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $asset = AppraisalAsset::create([
        'appraisal_request_id' => $record->id,
        'asset_type' => 'tanah',
        'address' => 'Readonly asset',
    ]);

    $requestFile = AppraisalRequestFile::create([
        'appraisal_request_id' => $record->id,
        'type' => 'permission',
        'path' => 'appraisal-requests/request/permission.pdf',
        'original_name' => 'permission.pdf',
        'mime' => 'application/pdf',
        'size' => 111,
    ]);

    $assetFile = AppraisalAssetFile::create([
        'appraisal_asset_id' => $asset->id,
        'type' => 'photo_front',
        'path' => 'appraisal-requests/assets/front.jpg',
        'original_name' => 'front.jpg',
        'mime' => 'image/jpeg',
        'size' => 222,
    ]);

    $base = "/admin/permohonan-penilaian/{$record->id}";

    $this->actingAs($admin)->get("{$base}/assets/create")->assertNotFound();
    $this->actingAs($admin)->post("{$base}/assets", [])->assertNotFound();
    $this->actingAs($admin)->get("{$base}/assets/{$asset->id}/edit")->assertNotFound();
    $this->actingAs($admin)->put("{$base}/assets/{$asset->id}", [])->assertNotFound();
    $this->actingAs($admin)->delete("{$base}/assets/{$asset->id}")->assertNotFound();
    $this->actingAs($admin)->post("{$base}/assets/{$asset->id}/files", [])->assertNotFound();
    $this->actingAs($admin)->delete("{$base}/assets/{$asset->id}/files/{$assetFile->id}")->assertNotFound();
    $this->actingAs($admin)->post("{$base}/files", [])->assertNotFound();
    $this->actingAs($admin)->delete("{$base}/files/{$requestFile->id}")->assertNotFound();
});

it('verifies docs from the vue admin workflow', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-VERIFY-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.verify-docs', $record))
        ->assertRedirect();

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::WaitingOffer);
    expect($record->verified_at)->not->toBeNull();
});

it('marks docs incomplete from the vue admin workflow', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-DOCS-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::WaitingOffer,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.docs-incomplete', $record))
        ->assertRedirect();

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::DocsIncomplete);
});

it('marks contract signed from the vue admin workflow', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-CONTRACT-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::WaitingSignature,
        'contract_status' => ContractStatusEnum::WaitingSignature,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.contract-signed', $record))
        ->assertRedirect();

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::ContractSigned);
    expect($record->contract_status)->toBe(ContractStatusEnum::ContractSigned);
});

it('sends an initial offer from the vue admin workflow', function () {
    Carbon::setTestNow('2026-05-10 09:00:00');

    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-OFFER-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Verified,
        'contract_status' => ContractStatusEnum::None,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.send-offer', $record), [
            'fee_total' => 18000000,
            'fee_has_dp' => true,
            'fee_dp_percent' => 50,
            'contract_sequence' => 3,
            'offer_validity_days' => 10,
        ])
        ->assertRedirect();

    $record->refresh();
    $latestNegotiation = $record->offerNegotiations()->latest('id')->first();

    expect($record->status)->toBe(AppraisalStatusEnum::OfferSent);
    expect($record->contract_status)->toBe(ContractStatusEnum::SentToClient);
    expect($record->contract_number)->toBe('00003/AGR/DP/05/2026');
    expect($record->contract_date?->toDateString())->toBe('2026-05-10');
    expect($record->fee_total)->toBe(18000000);
    expect($record->fee_has_dp)->toBeTrue();
    expect((float) $record->fee_dp_percent)->toBe(50.0);
    expect($latestNegotiation?->action)->toBe('offer_sent');
    expect($latestNegotiation?->offered_fee)->toBe(18000000);
    $requester->refresh();
    expect($requester->notifications()->count())->toBe(1);
    expect($requester->notifications()->first()->data['title'])->toBe('Penawaran baru tersedia');
    expect($requester->notifications()->first()->data['url'])->toContain("/permohonan-penilaian/{$record->id}/penawaran");

    Carbon::setTestNow();
});

it('sends a revised offer from the vue admin workflow when negotiation is active', function () {
    Carbon::setTestNow('2026-05-11 10:00:00');

    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-OFFER-REVISED-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::WaitingOffer,
        'contract_status' => ContractStatusEnum::Negotiation,
        'contract_sequence' => 7,
        'contract_number' => '00007/AGR/DP/05/2026',
        'requested_at' => now(),
    ]);

    $record->offerNegotiations()->create([
        'user_id' => $requester->id,
        'action' => 'counter_request',
        'round' => 1,
        'offered_fee' => 20000000,
        'expected_fee' => 19000000,
        'reason' => 'Mohon revisi fee',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.send-offer', $record), [
            'fee_total' => 19500000,
            'fee_has_dp' => false,
            'contract_sequence' => 7,
            'offer_validity_days' => 7,
        ])
        ->assertRedirect();

    $record->refresh();
    $latestNegotiation = $record->offerNegotiations()->latest('id')->first();

    expect($record->status)->toBe(AppraisalStatusEnum::OfferSent);
    expect($record->contract_status)->toBe(ContractStatusEnum::SentToClient);
    expect($record->fee_total)->toBe(19500000);
    expect($latestNegotiation?->action)->toBe('offer_revised');
    expect($latestNegotiation?->round)->toBe(1);
    expect($latestNegotiation?->offered_fee)->toBe(19500000);
    $requester->refresh();
    expect($requester->notifications()->count())->toBe(1);
    expect($requester->notifications()->first()->data['title'])->toBe('Revisi penawaran tersedia');
    expect($requester->notifications()->first()->data['url'])->toContain("/permohonan-penilaian/{$record->id}/penawaran");

    Carbon::setTestNow();
});

it('approves the latest user negotiation from the vue admin workflow', function () {
    Carbon::setTestNow('2026-05-12 11:00:00');

    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-APPROVE-NEGO-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::WaitingOffer,
        'contract_status' => ContractStatusEnum::Negotiation,
        'contract_sequence' => 9,
        'fee_total' => 25000000,
        'offer_validity_days' => 14,
        'requested_at' => now(),
    ]);

    $counterRequest = $record->offerNegotiations()->create([
        'user_id' => $requester->id,
        'action' => 'counter_request',
        'round' => 1,
        'offered_fee' => 25000000,
        'expected_fee' => 23000000,
        'reason' => 'Harap menyesuaikan fee',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.approve-latest-negotiation', $record))
        ->assertRedirect();

    $record->refresh();
    $latestNegotiation = $record->offerNegotiations()->latest('id')->first();

    expect($record->status)->toBe(AppraisalStatusEnum::WaitingSignature);
    expect($record->contract_status)->toBe(ContractStatusEnum::WaitingSignature);
    expect($record->fee_total)->toBe(23000000);
    expect($record->contract_number)->toBe('00009/AGR/DP/05/2026');
    expect($latestNegotiation?->action)->toBe('accepted');
    expect($latestNegotiation?->expected_fee)->toBe(23000000);
    expect($latestNegotiation?->selected_fee)->toBe(23000000);
    expect(data_get($latestNegotiation?->meta, 'counter_request_id'))->toBe($counterRequest->id);
    $requester->refresh();
    expect($requester->notifications()->count())->toBe(1);
    expect($requester->notifications()->first()->data['title'])->toBe('Negosiasi disetujui admin');
    expect($requester->notifications()->first()->data['url'])->toContain("/permohonan-penilaian/{$record->id}/kontrak");

    Carbon::setTestNow();
});

it('verifies payment from the vue admin workflow when the latest payment is ready', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-VERIFY-PAYMENT-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'contract_status' => ContractStatusEnum::ContractSigned,
        'requested_at' => now(),
    ]);

    Payment::create([
        'appraisal_request_id' => $record->id,
        'amount' => 15000000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'MID-VERIFY-001',
        'status' => 'paid',
        'proof_type' => 'gateway_id',
        'paid_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.appraisal-requests.actions.verify-payment', $record))
        ->assertRedirect();

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::ValuationOnProgress);
});

it('blocks payment verification from the vue admin workflow when payment is not ready', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-VERIFY-PAYMENT-002',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::ContractSigned,
        'contract_status' => ContractStatusEnum::ContractSigned,
        'requested_at' => now(),
    ]);

    Payment::create([
        'appraisal_request_id' => $record->id,
        'amount' => 15000000,
        'method' => 'gateway',
        'gateway' => 'midtrans',
        'external_payment_id' => 'MID-VERIFY-002',
        'status' => 'pending',
        'proof_type' => 'gateway_id',
    ]);

    $this
        ->actingAs($admin)
        ->from(route('admin.appraisal-requests.show', $record))
        ->post(route('admin.appraisal-requests.actions.verify-payment', $record))
        ->assertRedirect(route('admin.appraisal-requests.show', $record))
        ->assertSessionHas('error');

    $record->refresh();

    expect($record->status)->toBe(AppraisalStatusEnum::ContractSigned);
});

it('renders the basic edit page for admin users', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-EDIT-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.appraisal-requests.edit', $record))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/AppraisalRequests/Edit')
            ->where('record.request_number', 'REQ-EDIT-001'));
});

it('updates safe appraisal request fields from the vue admin form', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-UPDATE-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'report_type' => 'terinci',
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.appraisal-requests.update', $record), [
            'client_name' => 'PT Contoh Klien',
            'report_type' => 'singkat',
            'user_request_note' => 'Catatan user diperbarui',
            'notes' => 'Catatan internal diperbarui',
        ])
        ->assertRedirect(route('admin.appraisal-requests.show', $record));

    $record->refresh();

    expect($record->client_name)->toBe('PT Contoh Klien');
    expect($record->report_type)->toBe(\App\Enums\ReportTypeEnum::Ringkas);
    expect($record->contract_status)->toBe(ContractStatusEnum::None);
    expect($record->user_request_note)->toBe('Catatan user diperbarui');
    expect($record->notes)->toBe('Catatan internal diperbarui');
});

it('updates contract and fee fields from the vue admin form', function () {
    Carbon::setTestNow('2026-04-15 10:00:00');

    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-CONTRACT-FEE-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.appraisal-requests.update', $record), [
            'client_name' => 'PT Batch Empat',
            'report_type' => 'terinci',
            'contract_sequence' => 12,
            'contract_date' => '2026-04-20',
            'contract_status' => 'draft',
            'valuation_duration_days' => 25,
            'offer_validity_days' => 14,
            'fee_total' => 17500000,
            'fee_has_dp' => true,
            'fee_dp_percent' => 50,
            'user_request_note' => 'Butuh update kontrak',
            'notes' => 'Siap penawaran',
        ])
        ->assertRedirect(route('admin.appraisal-requests.show', $record));

    $record->refresh();

    expect($record->contract_sequence)->toBe(12);
    expect($record->contract_number)->toBe('00012/AGR/DP/04/2026');
    expect($record->contract_office_code)->toBe('0');
    expect($record->contract_month)->toBe(4);
    expect($record->contract_year)->toBe(2026);
    expect($record->contract_date?->toDateString())->toBe('2026-04-20');
    expect($record->contract_status)->toBe(ContractStatusEnum::Draft);
    expect($record->valuation_duration_days)->toBe(25);
    expect($record->offer_validity_days)->toBe(14);
    expect($record->fee_total)->toBe(17500000);
    expect($record->fee_has_dp)->toBeTrue();
    expect((float) $record->fee_dp_percent)->toBe(50.0);

    Carbon::setTestNow();
});

it('requires dp percent when dp is enabled on the vue admin edit form', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-DP-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->from(route('admin.appraisal-requests.edit', $record))
        ->put(route('admin.appraisal-requests.update', $record), [
            'client_name' => 'PT DP',
            'report_type' => 'terinci',
            'fee_has_dp' => true,
            'fee_dp_percent' => '',
        ])
        ->assertRedirect(route('admin.appraisal-requests.edit', $record))
        ->assertSessionHasErrors(['fee_dp_percent']);
});

it('validates report type on the vue admin edit form', function () {
    $admin = createAdminUser();
    $requester = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $record = AppraisalRequest::create([
        'user_id' => $requester->id,
        'request_number' => 'REQ-INVALID-001',
        'purpose' => PurposeEnum::JualBeli,
        'status' => AppraisalStatusEnum::Submitted,
        'requested_at' => now(),
    ]);

    $this
        ->actingAs($admin)
        ->from(route('admin.appraisal-requests.edit', $record))
        ->put(route('admin.appraisal-requests.update', $record), [
            'client_name' => 'PT Invalid',
            'report_type' => 'foo',
            'user_request_note' => 'x',
            'notes' => 'y',
        ])
        ->assertRedirect(route('admin.appraisal-requests.edit', $record))
        ->assertSessionHasErrors(['report_type']);
});

it('renders the admin provinces index in the vue workspace', function () {
    $admin = createAdminUser();

    Province::query()->create([
        'id' => '31',
        'name' => 'DKI Jakarta',
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.master-data.provinces.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Locations/Index')
            ->where('resource.key', 'provinces')
            ->where('records.meta.total', 1));
});

it('stores, updates, and deletes a province from the vue admin workspace', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->post(route('admin.master-data.provinces.store'), [
            'name' => 'Jawa Barat',
        ])
        ->assertRedirect(route('admin.master-data.provinces.index'));

    $province = Province::query()->firstOrFail();

    expect($province->id)->toBe('11');

    $this
        ->actingAs($admin)
        ->put(route('admin.master-data.provinces.update', $province), [
            'id' => '32',
            'name' => 'Jawa Barat Update',
        ])
        ->assertRedirect(route('admin.master-data.provinces.index'));

    expect($province->fresh()->name)->toBe('Jawa Barat Update');

    $this
        ->actingAs($admin)
        ->delete(route('admin.master-data.provinces.destroy', $province))
        ->assertRedirect(route('admin.master-data.provinces.index'));

    expect(Province::query()->find('11'))->toBeNull();
});

it('stores, updates, and deletes a regency from the vue admin workspace', function () {
    $admin = createAdminUser();
    $province = Province::query()->create([
        'id' => '33',
        'name' => 'Banten',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.master-data.regencies.store'), [
            'province_id' => $province->id,
            'name' => 'Kota Tangerang Selatan',
        ])
        ->assertRedirect(route('admin.master-data.regencies.index'));

    $regency = Regency::query()->firstOrFail();

    expect($regency->id)->toBe('3301');

    $this
        ->actingAs($admin)
        ->put(route('admin.master-data.regencies.update', $regency), [
            'id' => '3301',
            'province_id' => $province->id,
            'name' => 'Kota Tangerang Selatan Update',
        ])
        ->assertRedirect(route('admin.master-data.regencies.index'));

    expect($regency->fresh()->name)->toBe('Kota Tangerang Selatan Update');

    $this
        ->actingAs($admin)
        ->delete(route('admin.master-data.regencies.destroy', $regency))
        ->assertRedirect(route('admin.master-data.regencies.index'));

    expect(Regency::query()->find('3301'))->toBeNull();
});

it('stores, updates, and deletes a district from the vue admin workspace', function () {
    $admin = createAdminUser();
    $province = Province::query()->create([
        'id' => '34',
        'name' => 'DI Yogyakarta',
    ]);
    $regency = Regency::query()->create([
        'id' => '3401',
        'province_id' => $province->id,
        'name' => 'Kabupaten Kulon Progo',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.master-data.districts.store'), [
            'regency_id' => $regency->id,
            'name' => 'Temon',
        ])
        ->assertRedirect(route('admin.master-data.districts.index'));

    $district = District::query()->firstOrFail();

    expect($district->id)->toBe('3401001');

    $this
        ->actingAs($admin)
        ->put(route('admin.master-data.districts.update', $district), [
            'id' => '3401001',
            'regency_id' => $regency->id,
            'name' => 'Temon Update',
        ])
        ->assertRedirect(route('admin.master-data.districts.index'));

    expect($district->fresh()->name)->toBe('Temon Update');

    $this
        ->actingAs($admin)
        ->delete(route('admin.master-data.districts.destroy', $district))
        ->assertRedirect(route('admin.master-data.districts.index'));

    expect(District::query()->find('3401001'))->toBeNull();
});

it('stores, updates, and deletes a village from the vue admin workspace', function () {
    $admin = createAdminUser();
    $province = Province::query()->create([
        'id' => '35',
        'name' => 'Jawa Timur',
    ]);
    $regency = Regency::query()->create([
        'id' => '3578',
        'province_id' => $province->id,
        'name' => 'Kota Surabaya',
    ]);
    $district = District::query()->create([
        'id' => '3578010',
        'regency_id' => $regency->id,
        'name' => 'Tegalsari',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.master-data.villages.store'), [
            'district_id' => $district->id,
            'name' => 'Kedungdoro',
        ])
        ->assertRedirect(route('admin.master-data.villages.index'));

    $village = Village::query()->findOrFail('3578010001');

    $this
        ->actingAs($admin)
        ->put(route('admin.master-data.villages.update', $village), [
            'id' => '3578010001',
            'district_id' => $district->id,
            'name' => 'Kedungdoro Update',
        ])
        ->assertRedirect(route('admin.master-data.villages.index'));

    expect($village->fresh()->name)->toBe('Kedungdoro Update');

    $this
        ->actingAs($admin)
        ->delete(route('admin.master-data.villages.destroy', $village))
        ->assertRedirect(route('admin.master-data.villages.index'));

    expect(Village::query()->find('3578010001'))->toBeNull();
});

it('renders the village create and edit forms with workspace-aware location option urls', function () {
    $admin = createAdminUser();
    $province = Province::query()->create([
        'id' => '35',
        'name' => 'Jawa Timur',
    ]);
    $regency = Regency::query()->create([
        'id' => '3578',
        'province_id' => $province->id,
        'name' => 'Kota Surabaya',
    ]);
    $district = District::query()->create([
        'id' => '3578010',
        'regency_id' => $regency->id,
        'name' => 'Tegalsari',
    ]);
    $village = Village::query()->create([
        'id' => '3578010001',
        'district_id' => $district->id,
        'name' => 'Kedungdoro',
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.master-data.villages.create', ['district_id' => $district->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Locations/Form')
            ->where('resource.key', 'villages')
            ->where('showIdField', false)
            ->where('optionsUrl', route('admin.master-data.locations.options'))
            ->where('record.province_id', $province->id)
            ->where('record.regency_id', $regency->id)
            ->where('record.district_id', $district->id)
        );

    $this
        ->actingAs($admin)
        ->get(route('admin.master-data.villages.edit', $village))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Locations/Form')
            ->where('resource.key', 'villages')
            ->where('optionsUrl', route('admin.master-data.locations.options'))
            ->where('record.id', $village->id)
            ->where('record.name', 'Kedungdoro')
        );
});

it('renders the reviewer village create form with reviewer-scoped location option url', function () {
    $reviewer = createReviewerUser();
    $province = Province::query()->create([
        'id' => '35',
        'name' => 'Jawa Timur',
    ]);
    $regency = Regency::query()->create([
        'id' => '3578',
        'province_id' => $province->id,
        'name' => 'Kota Surabaya',
    ]);
    $district = District::query()->create([
        'id' => '3578010',
        'regency_id' => $regency->id,
        'name' => 'Tegalsari',
    ]);

    $this
        ->actingAs($reviewer)
        ->get(route('reviewer.master-data.villages.create', ['district_id' => $district->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Locations/Form')
            ->where('resource.key', 'villages')
            ->where('optionsUrl', route('reviewer.master-data.locations.options'))
        );
});

it('returns a generated location id preview for nested location resources', function () {
    $admin = createAdminUser();
    $province = Province::query()->create([
        'id' => '35',
        'name' => 'Jawa Timur',
    ]);
    Regency::query()->create([
        'id' => '3501',
        'province_id' => $province->id,
        'name' => 'Kabupaten Pacitan',
    ]);

    $this
        ->actingAs($admin)
        ->getJson(route('admin.master-data.locations.id-preview', [
            'type' => 'regency',
            'province_id' => $province->id,
        ]))
        ->assertOk()
        ->assertJson([
            'id' => '3502',
        ]);
});

it('returns filtered location options for village form selects', function () {
    $admin = createAdminUser();
    $province = Province::query()->create([
        'id' => '35',
        'name' => 'Jawa Timur',
    ]);
    Regency::query()->create([
        'id' => '3501',
        'province_id' => $province->id,
        'name' => 'Kabupaten Pacitan',
    ]);

    $this
        ->actingAs($admin)
        ->getJson(route('admin.master-data.locations.options', [
            'type' => 'regencies',
            'province_id' => $province->id,
        ]))
        ->assertOk()
        ->assertJsonPath('options.0.value', '3501')
        ->assertJsonPath('options.0.label', 'Kabupaten Pacitan (3501)');
});

it('returns filtered district options for district and village form selects', function () {
    $admin = createAdminUser();
    $province = Province::query()->create([
        'id' => '36',
        'name' => 'Banten',
    ]);
    $regency = Regency::query()->create([
        'id' => '3601',
        'province_id' => $province->id,
        'name' => 'Kabupaten Pandeglang',
    ]);
    District::query()->create([
        'id' => '3601001',
        'regency_id' => $regency->id,
        'name' => 'Labuan',
    ]);

    $this
        ->actingAs($admin)
        ->getJson(route('admin.master-data.locations.options', [
            'type' => 'districts',
            'regency_id' => $regency->id,
        ]))
        ->assertOk()
        ->assertJsonPath('options.0.value', '3601001')
        ->assertJsonPath('options.0.label', 'Labuan (3601001)');
});

it('renders the admin users index in the vue workspace', function () {
    $admin = createAdminUser();
    $user = User::factory()->create([
        'name' => 'Portal User',
        'email' => 'portal@example.com',
        'email_verified_at' => now(),
    ]);
    $user->assignRole('customer');
    $superAdmin = User::factory()->create([
        'name' => 'Hidden Super Admin',
        'email' => 'superhidden@example.com',
        'email_verified_at' => now(),
    ]);
    $superAdmin->assignRole('super_admin');

    $this
        ->actingAs($admin)
        ->get(route('admin.master-data.users.index', ['q' => 'portal@example.com']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Index')
            ->where('records.meta.total', 1)
            ->where('records.data.0.email', 'portal@example.com'));
});

it('renders the admin user detail page in the vue workspace', function () {
    $admin = createAdminUser();
    $user = User::factory()->create([
        'name' => 'Detail User',
        'email' => 'detail@example.com',
        'email_verified_at' => now(),
    ]);
    $user->assignRole('customer');

    $this
        ->actingAs($admin)
        ->get(route('admin.master-data.users.show', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Show')
            ->where('record.email', 'detail@example.com'));
});

it('allows admin to create a customer user from the vue admin workspace', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->post(route('admin.master-data.users.store'), [
            'name' => 'New Managed User',
            'email' => 'managed@example.com',
            'password' => 'password123',
            'email_verified_at' => now()->format('Y-m-d H:i:s'),
            'roles' => ['customer'],
        ])
        ->assertRedirect();

    $user = User::query()->where('email', 'managed@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->hasRole('customer'))->toBeTrue();
    expect($user->email_verified_at)->not->toBeNull();
});

it('blocks normal admin from assigning non-customer roles when creating users', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->from(route('admin.master-data.users.create'))
        ->post(route('admin.master-data.users.store'), [
            'name' => 'Escalation Attempt',
            'email' => 'escalation@example.com',
            'password' => 'password123',
            'email_verified_at' => now()->format('Y-m-d H:i:s'),
            'roles' => ['Reviewer'],
        ])
        ->assertRedirect(route('admin.master-data.users.create'))
        ->assertSessionHasErrors('roles.0');

    expect(User::query()->where('email', 'escalation@example.com')->exists())->toBeFalse();
});

it('allows admin to open the create user page with customer-only role options', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->get(route('admin.master-data.users.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Users/Form')
            ->where('roleOptions', [['value' => 'customer', 'label' => 'customer']])
            ->where('record.roles', ['customer']));
});

it('updates a managed user from the vue admin workspace', function () {
    $admin = createAdminUser();
    $user = User::factory()->create([
        'name' => 'Managed User',
        'email' => 'managed-update@example.com',
        'email_verified_at' => null,
    ]);
    $user->assignRole('customer');

    $this
        ->actingAs($admin)
        ->put(route('admin.master-data.users.update', $user), [
            'name' => 'Managed User Final',
            'email' => 'managed-update@example.com',
            'password' => 'password999',
            'email_verified_at' => now()->format('Y-m-d H:i:s'),
            'roles' => ['customer'],
        ])
        ->assertRedirect(route('admin.master-data.users.show', $user));

    $user->refresh();

    expect($user->name)->toBe('Managed User Final');
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->hasRole('customer'))->toBeTrue();
});

it('blocks normal admin from accessing a super admin user detail page', function () {
    $admin = createAdminUser();
    $superAdmin = createSuperAdminUser();

    $this
        ->actingAs($admin)
        ->get(route('admin.master-data.users.show', $superAdmin))
        ->assertForbidden();
});

it('allows super admin to delete a managed user from the vue admin workspace', function () {
    $superAdmin = createSuperAdminUser();
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $user->assignRole('customer');

    $this
        ->actingAs($superAdmin)
        ->delete(route('admin.master-data.users.destroy', $user))
        ->assertRedirect(route('admin.master-data.users.index'));

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});

it('blocks normal admin from deleting a user from the vue admin workspace', function () {
    $admin = createAdminUser();
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $user->assignRole('customer');

    $this
        ->actingAs($admin)
        ->delete(route('admin.master-data.users.destroy', $user))
        ->assertForbidden();

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('blocks normal admin from accessing the access control role index', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->get(route('admin.access-control.roles.index'))
        ->assertForbidden();
});

it('renders the admin roles index for super admin users', function () {
    $superAdmin = createSuperAdminUser();
    Role::findOrCreate('content_editor', 'web')->givePermissionTo(['view_article', 'create_article']);

    $this
        ->actingAs($superAdmin)
        ->get(route('admin.access-control.roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Roles/Index')
            ->where('records.meta.total', 5)
            ->where('workspaceMenusUrl', route('admin.access-control.system-menus.index')));
});

it('renders the admin workspace menu management index for super admin users', function () {
    $superAdmin = createSuperAdminUser();

    $this
        ->actingAs($superAdmin)
        ->get(route('admin.access-control.system-menus.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Roles/WorkspaceMenusIndex')
            ->where('summary.sections', count(SystemNavigation::menuManagementSections())));
});

it('renders the admin workspace menu edit page for super admin users', function () {
    $superAdmin = createSuperAdminUser();
    $reviewerRole = Role::findByName('Reviewer', 'web');

    $this
        ->actingAs($superAdmin)
        ->get(route('admin.access-control.system-menus.edit', $reviewerRole))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Roles/WorkspaceMenusForm')
            ->where('record.name', 'Reviewer')
            ->where('record.workspace_permissions', [
                SystemNavigation::ACCESS_REVIEWER_DASHBOARD,
                SystemNavigation::MANAGE_REVIEWER_REVIEWS,
                SystemNavigation::MANAGE_REVIEWER_COMPARABLES,
                SystemNavigation::MANAGE_ADMIN_REF_GUIDELINES,
                SystemNavigation::MANAGE_ADMIN_MASTER_DATA,
            ]));
});

it('renders the admin role detail for super admin users', function () {
    $superAdmin = createSuperAdminUser();
    $role = Role::findOrCreate('qa_admin', 'web');
    $role->syncPermissions(['view_article', 'update_article']);

    $this
        ->actingAs($superAdmin)
        ->get(route('admin.access-control.roles.show', $role))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Roles/Show')
            ->where('record.name', 'qa_admin')
            ->where('record.permissions_count', 2));
});

it('updates workspace menu permissions for a role from the admin workspace', function () {
    $superAdmin = createSuperAdminUser();
    $reviewerRole = Role::findByName('Reviewer', 'web');

    $this
        ->actingAs($superAdmin)
        ->put(route('admin.access-control.system-menus.update', $reviewerRole), [
            'workspace_permissions' => [
                SystemNavigation::ACCESS_REVIEWER_DASHBOARD,
                SystemNavigation::MANAGE_REVIEWER_REVIEWS,
                SystemNavigation::MANAGE_REVIEWER_COMPARABLES,
                SystemNavigation::MANAGE_ADMIN_REF_GUIDELINES,
            ],
        ])
        ->assertRedirect(route('admin.access-control.system-menus.edit', $reviewerRole));

    $reviewerRole->refresh();

    expect($reviewerRole->hasPermissionTo(SystemNavigation::MANAGE_ADMIN_REF_GUIDELINES))->toBeTrue();
    expect($reviewerRole->hasPermissionTo(SystemNavigation::MANAGE_ADMIN_MASTER_DATA))->toBeFalse();

    $reviewer = createReviewerUser();

    $this
        ->actingAs($reviewer)
        ->get(route('reviewer.master-data.provinces.index'))
        ->assertForbidden();

    $this
        ->actingAs($reviewer)
        ->get(route('reviewer.ref-guidelines.guideline-sets.index'))
        ->assertOk();
});

it('creates a role from the vue admin access control workspace', function () {
    $superAdmin = createSuperAdminUser();

    $this
        ->actingAs($superAdmin)
        ->post(route('admin.access-control.roles.store'), [
            'name' => 'finance_admin',
            'guard_name' => 'web',
            'permissions' => ['view_article', 'create_article'],
        ])
        ->assertRedirect();

    $role = Role::query()->where('name', 'finance_admin')->first();

    expect($role)->not->toBeNull();
    expect($role->hasPermissionTo('view_article'))->toBeTrue();
    expect($role->hasPermissionTo('create_article'))->toBeTrue();
});

it('updates a role from the vue admin access control workspace', function () {
    $superAdmin = createSuperAdminUser();
    $role = Role::findOrCreate('ops_admin', 'web');
    $role->syncPermissions(['view_article']);

    $this
        ->actingAs($superAdmin)
        ->put(route('admin.access-control.roles.update', $role), [
            'name' => 'ops_admin',
            'guard_name' => 'web',
            'permissions' => ['view_article', 'update_article'],
        ])
        ->assertRedirect(route('admin.access-control.roles.show', $role));

    $role->refresh();

    expect($role->hasPermissionTo('view_article'))->toBeTrue();
    expect($role->hasPermissionTo('update_article'))->toBeTrue();
    expect($role->permissions()->count())->toBe(2);
});

it('deletes a role from the vue admin access control workspace', function () {
    $superAdmin = createSuperAdminUser();
    $role = Role::findOrCreate('temporary_role', 'web');

    $this
        ->actingAs($superAdmin)
        ->delete(route('admin.access-control.roles.destroy', $role))
        ->assertRedirect(route('admin.access-control.roles.index'));

    expect(Role::query()->where('name', 'temporary_role')->exists())->toBeFalse();
});

it('renders the guideline sets index in the vue admin workspace', function () {
    $admin = createAdminUser();

    GuidelineSet::query()->create([
        'name' => 'Pedoman 2026',
        'year' => 2026,
        'description' => 'Acuan appraisal tahun 2026.',
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.ref-guidelines.guideline-sets.index', ['q' => '2026']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/GuidelineSets/Index')
            ->where('records.meta.total', 1)
            ->where('records.data.0.name', 'Pedoman 2026'));
});

it('exports guideline sets from the vue admin workspace', function () {
    $admin = createAdminUser();

    GuidelineSet::query()->create([
        'name' => 'Pedoman Export 2026',
        'year' => 2026,
        'description' => 'Acuan export guideline.',
        'is_active' => true,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.ref-guidelines.guideline-sets.export', ['q' => 'Export']));

    $response->assertOk();

    expect((string) $response->headers->get('content-disposition'))
        ->toContain('attachment;')
        ->toContain('guideline-sets-');
});

it('creates a guideline set from the vue admin workspace', function () {
    $admin = createAdminUser();

    $this
        ->actingAs($admin)
        ->post(route('admin.ref-guidelines.guideline-sets.store'), [
            'name' => 'Pedoman 2027',
            'year' => 2027,
            'description' => 'Acuan appraisal tahun 2027.',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.ref-guidelines.guideline-sets.index'));

    $record = GuidelineSet::query()->where('name', 'Pedoman 2027')->first();

    expect($record)->not->toBeNull();
    expect($record->year)->toBe(2027);
    expect($record->is_active)->toBeTrue();
});

it('updates a guideline set from the vue admin workspace and deactivates the old active set', function () {
    $admin = createAdminUser();
    $active = GuidelineSet::query()->create([
        'name' => 'Pedoman Aktif 2025',
        'year' => 2025,
        'description' => 'Guideline lama.',
        'is_active' => true,
    ]);
    $target = GuidelineSet::query()->create([
        'name' => 'Pedoman Draft 2026',
        'year' => 2026,
        'description' => 'Guideline draft.',
        'is_active' => false,
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.ref-guidelines.guideline-sets.update', $target), [
            'name' => 'Pedoman Final 2026',
            'year' => 2026,
            'description' => 'Guideline final.',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.ref-guidelines.guideline-sets.index'));

    $active->refresh();
    $target->refresh();

    expect($target->name)->toBe('Pedoman Final 2026');
    expect($target->is_active)->toBeTrue();
    expect($active->is_active)->toBeFalse();
});

it('deletes a guideline set from the vue admin workspace', function () {
    $admin = createAdminUser();
    $record = GuidelineSet::query()->create([
        'name' => 'Pedoman Hapus',
        'year' => 2028,
        'description' => null,
        'is_active' => false,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.ref-guidelines.guideline-sets.destroy', $record))
        ->assertRedirect(route('admin.ref-guidelines.guideline-sets.index'));

    expect(GuidelineSet::query()->whereKey($record->id)->exists())->toBeFalse();
});

it('renders the valuation settings index in the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman Nilai 2026',
        'year' => 2026,
        'description' => null,
        'is_active' => true,
    ]);

    ValuationSetting::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2026,
        'key' => ValuationSetting::KEY_PPN_PERCENT,
        'label' => 'PPN (%)',
        'value_number' => 12,
        'value_text' => 'Standar 2026',
        'notes' => 'Dipakai reviewer.',
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.ref-guidelines.valuation-settings.index', ['key' => ValuationSetting::KEY_PPN_PERCENT]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/ValuationSettings/Index')
            ->where('records.meta.total', 1)
            ->where('records.data.0.key', ValuationSetting::KEY_PPN_PERCENT));
});

it('creates a valuation setting from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman 2029',
        'year' => 2029,
        'description' => null,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.ref-guidelines.valuation-settings.store'), [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2029,
            'key' => ValuationSetting::KEY_PPN_PERCENT,
            'label' => 'PPN (%)',
            'value_number' => 13,
            'value_text' => 'Tarif 2029',
            'notes' => 'Catatan setting baru.',
        ])
        ->assertRedirect(route('admin.ref-guidelines.valuation-settings.index'));

    $record = ValuationSetting::query()
        ->where('guideline_set_id', $guidelineSet->id)
        ->where('year', 2029)
        ->where('key', ValuationSetting::KEY_PPN_PERCENT)
        ->first();

    expect($record)->not->toBeNull();
    expect((float) $record->value_number)->toBe(13.0);
});

it('updates a valuation setting from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman 2030',
        'year' => 2030,
        'description' => null,
        'is_active' => true,
    ]);
    $record = ValuationSetting::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2030,
        'key' => ValuationSetting::KEY_PPN_PERCENT,
        'label' => 'PPN (%)',
        'value_number' => 11,
        'value_text' => null,
        'notes' => 'Nilai awal.',
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.ref-guidelines.valuation-settings.update', $record), [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2030,
            'key' => ValuationSetting::KEY_PPN_PERCENT,
            'label' => 'PPN Final (%)',
            'value_number' => 12,
            'value_text' => 'Tarif final',
            'notes' => 'Sudah diperbarui.',
        ])
        ->assertRedirect(route('admin.ref-guidelines.valuation-settings.index'));

    $record->refresh();

    expect($record->label)->toBe('PPN Final (%)');
    expect((float) $record->value_number)->toBe(12.0);
    expect($record->value_text)->toBe('Tarif final');
});

it('deletes a valuation setting from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman 2031',
        'year' => 2031,
        'description' => null,
        'is_active' => false,
    ]);
    $record = ValuationSetting::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2031,
        'key' => ValuationSetting::KEY_PPN_PERCENT,
        'label' => 'PPN (%)',
        'value_number' => 10,
        'value_text' => null,
        'notes' => null,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.ref-guidelines.valuation-settings.destroy', $record))
        ->assertRedirect(route('admin.ref-guidelines.valuation-settings.index'));

    expect(ValuationSetting::query()->whereKey($record->id)->exists())->toBeFalse();
});

it('renders the construction cost index list in the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman IKK 2026',
        'year' => 2026,
        'description' => null,
        'is_active' => true,
    ]);
    $province = Province::query()->create([
        'id' => '31',
        'name' => 'DKI Jakarta',
    ]);
    Regency::query()->create([
        'id' => '3171',
        'province_id' => $province->id,
        'name' => 'Jakarta Pusat',
    ]);
    ConstructionCostIndex::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2026,
        'region_code' => '3171',
        'region_name' => 'Jakarta Pusat',
        'ikk_value' => 1.1250,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.ref-guidelines.construction-cost-indices.index', ['province_id' => $province->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/ConstructionCostIndices/Index')
            ->where('records.meta.total', 1)
            ->where('records.data.0.region_name', 'Jakarta Pusat'));
});

it('creates a construction cost index from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman IKK 2027',
        'year' => 2027,
        'description' => null,
        'is_active' => true,
    ]);
    $province = Province::query()->create([
        'id' => '32',
        'name' => 'Jawa Barat',
    ]);
    $regency = Regency::query()->create([
        'id' => '3273',
        'province_id' => $province->id,
        'name' => 'Kota Bandung',
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.ref-guidelines.construction-cost-indices.store'), [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2027,
            'region_code' => $regency->id,
            'ikk_value' => 1.2345,
        ])
        ->assertRedirect(route('admin.ref-guidelines.construction-cost-indices.index'));

    $record = ConstructionCostIndex::query()->where('guideline_set_id', $guidelineSet->id)->where('region_code', $regency->id)->first();

    expect($record)->not->toBeNull();
    expect($record->region_name)->toBe('Kota Bandung');
    expect((float) $record->ikk_value)->toBe(1.2345);
});

it('imports construction cost indices from excel in the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman IKK Import 2030',
        'year' => 2030,
        'description' => null,
        'is_active' => true,
    ]);
    $province = Province::query()->create([
        'id' => '31',
        'name' => 'DKI Jakarta',
    ]);
    Regency::query()->create([
        'id' => '3171',
        'province_id' => $province->id,
        'name' => 'Jakarta Pusat',
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'ikk-import.csv',
        implode("\n", [
            'kode,nama_provinsi_kota_kabupaten,ikk_mappi',
            '3171,Jakarta Pusat,1.4321',
        ])
    );

    $this
        ->actingAs($admin)
        ->post(route('admin.ref-guidelines.construction-cost-indices.import'), [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2030,
            'skip_province_rows' => true,
            'require_regency' => true,
            'file' => $file,
        ])
        ->assertRedirect(route('admin.ref-guidelines.construction-cost-indices.index', [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2030,
        ]));

    $record = ConstructionCostIndex::query()
        ->where('guideline_set_id', $guidelineSet->id)
        ->where('year', 2030)
        ->where('region_code', '3171')
        ->first();

    expect($record)->not->toBeNull();
    expect($record->region_name)->toBe('Jakarta Pusat');
    expect((float) $record->ikk_value)->toBe(1.4321);
});

it('exports construction cost indices from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman Export IKK 2030',
        'year' => 2030,
        'description' => null,
        'is_active' => true,
    ]);

    ConstructionCostIndex::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2030,
        'region_code' => '3171',
        'region_name' => 'Jakarta Pusat',
        'ikk_value' => 1.4321,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.ref-guidelines.construction-cost-indices.export', [
            'guideline_set_id' => $guidelineSet->id,
        ]));

    $response->assertOk();

    expect((string) $response->headers->get('content-disposition'))
        ->toContain('attachment;')
        ->toContain('ikk-');
});

it('updates a construction cost index from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman IKK 2028',
        'year' => 2028,
        'description' => null,
        'is_active' => true,
    ]);
    $province = Province::query()->create([
        'id' => '33',
        'name' => 'Jawa Tengah',
    ]);
    $regency = Regency::query()->create([
        'id' => '3374',
        'province_id' => $province->id,
        'name' => 'Kota Semarang',
    ]);
    $record = ConstructionCostIndex::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2028,
        'region_code' => $regency->id,
        'region_name' => $regency->name,
        'ikk_value' => 1.0100,
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.ref-guidelines.construction-cost-indices.update', $record), [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2028,
            'region_code' => $regency->id,
            'ikk_value' => 1.1111,
        ])
        ->assertRedirect(route('admin.ref-guidelines.construction-cost-indices.index'));

    $record->refresh();

    expect((float) $record->ikk_value)->toBe(1.1111);
});

it('deletes a construction cost index from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman IKK 2029',
        'year' => 2029,
        'description' => null,
        'is_active' => false,
    ]);
    $province = Province::query()->create([
        'id' => '34',
        'name' => 'DI Yogyakarta',
    ]);
    $regency = Regency::query()->create([
        'id' => '3471',
        'province_id' => $province->id,
        'name' => 'Kota Yogyakarta',
    ]);
    $record = ConstructionCostIndex::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2029,
        'region_code' => $regency->id,
        'region_name' => $regency->name,
        'ikk_value' => 1.0500,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.ref-guidelines.construction-cost-indices.destroy', $record))
        ->assertRedirect(route('admin.ref-guidelines.construction-cost-indices.index'));

    expect(ConstructionCostIndex::query()->whereKey($record->id)->exists())->toBeFalse();
});

it('renders the cost elements list in the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman Cost 2026',
        'year' => 2026,
        'description' => null,
        'is_active' => true,
    ]);

    CostElement::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2026,
        'base_region' => 'DKI Jakarta',
        'group' => 'Struktur',
        'element_code' => 'STR-001',
        'element_name' => 'Pondasi',
        'building_type' => 'Rumah Tinggal',
        'building_class' => 'A',
        'storey_pattern' => '1-2',
        'unit' => 'm2',
        'unit_cost' => 250000,
        'spec_json' => ['line_order' => 1],
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.ref-guidelines.cost-elements.index', ['group' => 'Struktur']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/CostElements/Index')
            ->where('records.meta.total', 1)
            ->where('records.data.0.element_name', 'Pondasi'));
});

it('creates a cost element from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman Cost 2027',
        'year' => 2027,
        'description' => null,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.ref-guidelines.cost-elements.store'), [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2027,
            'base_region' => 'DKI Jakarta',
            'group' => 'Arsitektur',
            'element_code' => 'ARC-101',
            'element_name' => 'Dinding',
            'building_type' => 'Ruko',
            'building_class' => 'B',
            'storey_pattern' => '1-3',
            'unit' => 'm2',
            'unit_cost' => 325000,
            'spec_json' => '{"line_order":2,"material_spec":"Bata ringan"}',
        ])
        ->assertRedirect(route('admin.ref-guidelines.cost-elements.index'));

    $record = CostElement::query()->where('element_code', 'ARC-101')->first();

    expect($record)->not->toBeNull();
    expect($record->element_name)->toBe('Dinding');
    expect($record->spec_json)->toBe(['line_order' => 2, 'material_spec' => 'Bata ringan']);
});

it('imports cost elements from excel in the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman Cost Import 2032',
        'year' => 2032,
        'description' => null,
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'cost-elements-import.csv',
        implode("\n", [
            'group,element_code,element_name,building_type,building_class,storey_pattern,unit,unit_cost,spec_json',
            'PONDASI,CE-001,Pondasi Batu Kali,RUMAH,GRADE_A,1-2,m2,450000,',
            'STRUKTUR,CE-002,Beton Bertulang 1 lantai,RUMAH,GRADE_A,1-2,m2,650000,',
        ])
    );

    $this
        ->actingAs($admin)
        ->post(route('admin.ref-guidelines.cost-elements.import'), [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2032,
            'base_region' => 'DKI Jakarta',
            'file' => $file,
        ])
        ->assertRedirect(route('admin.ref-guidelines.cost-elements.index', [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2032,
            'base_region' => 'DKI Jakarta',
        ]));

    expect(
        (int) CostElement::query()
            ->where('guideline_set_id', $guidelineSet->id)
            ->where('year', 2032)
            ->where('base_region', 'DKI Jakarta')
            ->where('element_code', 'CE-001')
            ->value('unit_cost')
    )->toBe(450000);

    expect(
        CostElement::query()
            ->where('guideline_set_id', $guidelineSet->id)
            ->where('year', 2032)
            ->where('element_code', 'CE-002')
            ->value('element_name')
    )->toBe('Beton Bertulang 1 lantai');
});

it('exports cost elements from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman Export Cost 2032',
        'year' => 2032,
        'description' => null,
        'is_active' => true,
    ]);

    CostElement::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2032,
        'base_region' => 'DKI Jakarta',
        'group' => 'PONDASI',
        'element_code' => 'CE-001',
        'element_name' => 'Pondasi Batu Kali',
        'building_type' => 'RUMAH',
        'building_class' => 'GRADE_A',
        'storey_pattern' => '1-2',
        'unit' => 'm2',
        'unit_cost' => 450000,
        'spec_json' => ['material_spec' => 'Batu kali'],
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.ref-guidelines.cost-elements.export', [
            'guideline_set_id' => $guidelineSet->id,
        ]));

    $response->assertOk();

    expect((string) $response->headers->get('content-disposition'))
        ->toContain('attachment;')
        ->toContain('cost-elements-');
});

it('updates a cost element from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman Cost 2028',
        'year' => 2028,
        'description' => null,
        'is_active' => true,
    ]);
    $record = CostElement::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2028,
        'base_region' => 'DKI Jakarta',
        'group' => 'MEP',
        'element_code' => 'MEP-010',
        'element_name' => 'Plumbing',
        'building_type' => null,
        'building_class' => null,
        'storey_pattern' => null,
        'unit' => 'm2',
        'unit_cost' => 450000,
        'spec_json' => ['line_order' => 10],
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.ref-guidelines.cost-elements.update', $record), [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2028,
            'base_region' => 'DKI Jakarta',
            'group' => 'MEP',
            'element_code' => 'MEP-010',
            'element_name' => 'Plumbing Final',
            'building_type' => 'Hotel',
            'building_class' => 'Premium',
            'storey_pattern' => '>=6',
            'unit' => 'm2',
            'unit_cost' => 550000,
            'spec_json' => '{"line_order":11}',
        ])
        ->assertRedirect(route('admin.ref-guidelines.cost-elements.index'));

    $record->refresh();

    expect($record->element_name)->toBe('Plumbing Final');
    expect((int) $record->unit_cost)->toBe(550000);
    expect($record->spec_json)->toBe(['line_order' => 11]);
});

it('deletes a cost element from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman Cost 2029',
        'year' => 2029,
        'description' => null,
        'is_active' => false,
    ]);
    $record = CostElement::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2029,
        'base_region' => 'DKI Jakarta',
        'group' => 'Finishing',
        'element_code' => 'FIN-001',
        'element_name' => 'Cat',
        'building_type' => null,
        'building_class' => null,
        'storey_pattern' => null,
        'unit' => 'm2',
        'unit_cost' => 120000,
        'spec_json' => null,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.ref-guidelines.cost-elements.destroy', $record))
        ->assertRedirect(route('admin.ref-guidelines.cost-elements.index'));

    expect(CostElement::query()->whereKey($record->id)->exists())->toBeFalse();
});

it('renders the floor indices list in the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman IL 2026',
        'year' => 2026,
        'description' => null,
        'is_active' => true,
    ]);

    FloorIndex::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2026,
        'building_class' => 'DEFAULT',
        'floor_count' => 2,
        'il_value' => 1.0500,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.ref-guidelines.floor-indices.index', ['building_class' => 'DEFAULT']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/FloorIndices/Index')
            ->where('records.meta.total', 1)
            ->where('records.data.0.floor_count', 2));
});

it('creates a floor index from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman IL 2027',
        'year' => 2027,
        'description' => null,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.ref-guidelines.floor-indices.store'), [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2027,
            'building_class' => 'GRADE_A',
            'floor_count' => 5,
            'il_value' => 1.1250,
        ])
        ->assertRedirect(route('admin.ref-guidelines.floor-indices.index'));

    $record = FloorIndex::query()
        ->where('guideline_set_id', $guidelineSet->id)
        ->where('building_class', 'GRADE_A')
        ->where('floor_count', 5)
        ->first();

    expect($record)->not->toBeNull();
    expect((float) $record->il_value)->toBe(1.1250);
});

it('imports floor indices from excel in the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman IL Import 2031',
        'year' => 2031,
        'description' => null,
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'floor-indices-import.csv',
        implode("\n", [
            'building_class,floor_count,il_value',
            'RUKO,3,1.0000',
            'OFFICE,8,1.0000',
        ])
    );

    $this
        ->actingAs($admin)
        ->post(route('admin.ref-guidelines.floor-indices.import'), [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2031,
            'file' => $file,
        ])
        ->assertRedirect(route('admin.ref-guidelines.floor-indices.index', [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2031,
        ]));

    expect(
        (float) FloorIndex::query()
            ->where('guideline_set_id', $guidelineSet->id)
            ->where('year', 2031)
            ->where('building_class', 'RUKO')
            ->where('floor_count', 3)
            ->value('il_value')
    )->toBe(1.0);

    expect(
        (float) FloorIndex::query()
            ->where('guideline_set_id', $guidelineSet->id)
            ->where('year', 2031)
            ->where('building_class', 'OFFICE')
            ->where('floor_count', 8)
            ->value('il_value')
    )->toBe(1.0);
});

it('exports floor indices from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman Export IL 2031',
        'year' => 2031,
        'description' => null,
        'is_active' => true,
    ]);

    FloorIndex::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2031,
        'building_class' => 'RUKO',
        'floor_count' => 3,
        'il_value' => 1.0,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.ref-guidelines.floor-indices.export', [
            'guideline_set_id' => $guidelineSet->id,
        ]));

    $response->assertOk();

    expect((string) $response->headers->get('content-disposition'))
        ->toContain('attachment;')
        ->toContain('floor-indices-');
});

it('updates a floor index from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman IL 2028',
        'year' => 2028,
        'description' => null,
        'is_active' => true,
    ]);
    $record = FloorIndex::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2028,
        'building_class' => 'GRADE_B',
        'floor_count' => 3,
        'il_value' => 1.0100,
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.ref-guidelines.floor-indices.update', $record), [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2028,
            'building_class' => 'GRADE_B',
            'floor_count' => 3,
            'il_value' => 1.0750,
        ])
        ->assertRedirect(route('admin.ref-guidelines.floor-indices.index'));

    $record->refresh();

    expect((float) $record->il_value)->toBe(1.0750);
});

it('deletes a floor index from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman IL 2029',
        'year' => 2029,
        'description' => null,
        'is_active' => false,
    ]);
    $record = FloorIndex::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2029,
        'building_class' => 'DEFAULT',
        'floor_count' => 1,
        'il_value' => 1.0000,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.ref-guidelines.floor-indices.destroy', $record))
        ->assertRedirect(route('admin.ref-guidelines.floor-indices.index'));

    expect(FloorIndex::query()->whereKey($record->id)->exists())->toBeFalse();
});

it('renders the mappi rcn list in the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman RCN 2026',
        'year' => 2026,
        'description' => null,
        'is_active' => true,
    ]);

    MappiRcnStandard::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2026,
        'reference_region' => 'DKI Jakarta',
        'building_type' => 'RUKO',
        'building_class' => null,
        'storey_pattern' => '1-2',
        'rcn_value' => 4250000,
        'notes' => 'Standar ruko.',
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.ref-guidelines.mappi-rcn-standards.index', ['building_type' => 'RUKO']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/MappiRcnStandards/Index')
            ->where('records.meta.total', 1)
            ->where('records.data.0.building_type', 'RUKO'));
});

it('creates a mappi rcn standard from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman RCN 2027',
        'year' => 2027,
        'description' => null,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.ref-guidelines.mappi-rcn-standards.store'), [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2027,
            'reference_region' => 'DKI Jakarta',
            'building_type' => 'BANGUNAN_GEDUNG_BERTINGKAT',
            'building_class' => 'LOW_RISE',
            'storey_pattern' => '3-5',
            'rcn_value' => 6500000,
            'notes' => 'RCN low rise.',
        ])
        ->assertRedirect(route('admin.ref-guidelines.mappi-rcn-standards.index'));

    $record = MappiRcnStandard::query()
        ->where('guideline_set_id', $guidelineSet->id)
        ->where('building_type', 'BANGUNAN_GEDUNG_BERTINGKAT')
        ->where('storey_pattern', '3-5')
        ->first();

    expect($record)->not->toBeNull();
    expect((int) $record->rcn_value)->toBe(6500000);
});

it('imports mappi rcn standards from excel in the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman RCN Import 2033',
        'year' => 2033,
        'description' => null,
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'mappi-rcn-import.csv',
        implode("\n", [
            'building_type,building_class,storey_pattern,rcn_value,notes',
            'RUKO,,1-2,4250000,Import ruko',
            'BANGUNAN_GEDUNG_BERTINGKAT,LOW_RISE,3-5,6500000,Import low rise',
        ])
    );

    $this
        ->actingAs($admin)
        ->post(route('admin.ref-guidelines.mappi-rcn-standards.import'), [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2033,
            'reference_region' => 'DKI Jakarta',
            'file' => $file,
        ])
        ->assertRedirect(route('admin.ref-guidelines.mappi-rcn-standards.index', [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2033,
        ]));

    expect(
        (int) MappiRcnStandard::query()
            ->where('guideline_set_id', $guidelineSet->id)
            ->where('year', 2033)
            ->where('building_type', 'RUKO')
            ->value('rcn_value')
    )->toBe(4250000);

    expect(
        MappiRcnStandard::query()
            ->where('guideline_set_id', $guidelineSet->id)
            ->where('year', 2033)
            ->where('building_type', 'BANGUNAN_GEDUNG_BERTINGKAT')
            ->value('notes')
    )->toBe('Import low rise');
});

it('exports mappi rcn standards from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman Export RCN 2033',
        'year' => 2033,
        'description' => null,
        'is_active' => true,
    ]);

    MappiRcnStandard::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2033,
        'reference_region' => 'DKI Jakarta',
        'building_type' => 'RUKO',
        'building_class' => null,
        'storey_pattern' => '1-2',
        'rcn_value' => 4250000,
        'notes' => 'Export ruko',
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.ref-guidelines.mappi-rcn-standards.export', [
            'guideline_set_id' => $guidelineSet->id,
        ]));

    $response->assertOk();

    expect((string) $response->headers->get('content-disposition'))
        ->toContain('attachment;')
        ->toContain('mappi-rcn-standards-');
});

it('updates a mappi rcn standard from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman RCN 2028',
        'year' => 2028,
        'description' => null,
        'is_active' => true,
    ]);
    $record = MappiRcnStandard::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2028,
        'reference_region' => 'DKI Jakarta',
        'building_type' => 'MODEL_APARTEMEN',
        'building_class' => 'GRADE_B',
        'storey_pattern' => '>=6',
        'rcn_value' => 7800000,
        'notes' => 'Awal.',
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.ref-guidelines.mappi-rcn-standards.update', $record), [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2028,
            'reference_region' => 'DKI Jakarta',
            'building_type' => 'MODEL_APARTEMEN',
            'building_class' => 'GRADE_B',
            'storey_pattern' => '>=6',
            'rcn_value' => 8000000,
            'notes' => 'Revisi final.',
        ])
        ->assertRedirect(route('admin.ref-guidelines.mappi-rcn-standards.index'));

    $record->refresh();

    expect((int) $record->rcn_value)->toBe(8000000);
    expect($record->notes)->toBe('Revisi final.');
});

it('deletes a mappi rcn standard from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman RCN 2029',
        'year' => 2029,
        'description' => null,
        'is_active' => false,
    ]);
    $record = MappiRcnStandard::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2029,
        'reference_region' => 'DKI Jakarta',
        'building_type' => 'BANGUNAN_GUDANG',
        'building_class' => null,
        'storey_pattern' => null,
        'rcn_value' => 3900000,
        'notes' => null,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.ref-guidelines.mappi-rcn-standards.destroy', $record))
        ->assertRedirect(route('admin.ref-guidelines.mappi-rcn-standards.index'));

    expect(MappiRcnStandard::query()->whereKey($record->id)->exists())->toBeFalse();
});

it('renders the building economic life list in the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman BEL 2026',
        'year' => 2026,
        'description' => null,
        'is_active' => true,
    ]);

    BuildingEconomicLife::query()->create([
        'guideline_item_id' => $guidelineSet->id,
        'year' => 2026,
        'category' => 'Rumah Tinggal',
        'sub_category' => 'Menengah',
        'building_type' => 'RUMAH',
        'building_class' => 'GRADE_A',
        'storey_min' => 1,
        'storey_max' => 2,
        'economic_life' => 40,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.ref-guidelines.building-economic-lives.index', ['category' => 'Rumah Tinggal']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/BuildingEconomicLives/Index')
            ->where('records.meta.total', 1)
            ->where('records.data.0.economic_life', 40));
});

it('exports building economic life records from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman BEL Export 2026',
        'year' => 2026,
        'description' => null,
        'is_active' => true,
    ]);

    BuildingEconomicLife::query()->create([
        'guideline_item_id' => $guidelineSet->id,
        'year' => 2026,
        'category' => 'Rumah Tinggal',
        'sub_category' => 'Menengah',
        'building_type' => 'RUMAH',
        'building_class' => 'GRADE_A',
        'storey_min' => 1,
        'storey_max' => 2,
        'economic_life' => 40,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.ref-guidelines.building-economic-lives.export', [
            'guideline_item_id' => $guidelineSet->id,
        ]));

    $response->assertOk();

    expect((string) $response->headers->get('content-disposition'))
        ->toContain('attachment;')
        ->toContain('building-economic-lives-');
});

it('creates a building economic life record from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman BEL 2027',
        'year' => 2027,
        'description' => null,
        'is_active' => true,
    ]);

    $this
        ->actingAs($admin)
        ->post(route('admin.ref-guidelines.building-economic-lives.store'), [
            'guideline_item_id' => $guidelineSet->id,
            'year' => 2027,
            'category' => 'Ruko',
            'sub_category' => 'Komersial',
            'building_type' => 'RUKO',
            'building_class' => 'GRADE_B',
            'storey_min' => 2,
            'storey_max' => 4,
            'economic_life' => 35,
        ])
        ->assertRedirect(route('admin.ref-guidelines.building-economic-lives.index'));

    $record = BuildingEconomicLife::query()
        ->where('guideline_item_id', $guidelineSet->id)
        ->where('category', 'Ruko')
        ->where('building_class', 'GRADE_B')
        ->first();

    expect($record)->not->toBeNull();
    expect((int) $record->economic_life)->toBe(35);
});

it('imports building economic life records from excel in the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman BEL Import 2030',
        'year' => 2030,
        'description' => null,
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->createWithContent(
        'building-economic-life-import.csv',
        implode("\n", [
            'category,sub_category,building_type,building_class,storey_min,storey_max,economic_life',
            'BANGUNAN KANTOR,Bangunan Kantor <= 4 lantai,KANTOR,, ,4,40',
            'BANGUNAN KANTOR,Bangunan Kantor >= 5 lantai,KANTOR,,5,,50',
        ])
    );

    $this
        ->actingAs($admin)
        ->post(route('admin.ref-guidelines.building-economic-lives.import'), [
            'guideline_item_id' => $guidelineSet->id,
            'year' => 2030,
            'file' => $file,
        ])
        ->assertRedirect(route('admin.ref-guidelines.building-economic-lives.index', [
            'guideline_item_id' => $guidelineSet->id,
            'year' => 2030,
        ]));

    expect(
        BuildingEconomicLife::query()
            ->where('guideline_item_id', $guidelineSet->id)
            ->where('year', 2030)
            ->where('category', 'BANGUNAN KANTOR')
            ->where('sub_category', 'Bangunan Kantor <= 4 lantai')
            ->where('building_type', 'KANTOR')
            ->whereNull('building_class')
            ->whereNull('storey_min')
            ->where('storey_max', 4)
            ->value('economic_life')
    )->toBe(40);

    expect(
        BuildingEconomicLife::query()
            ->where('guideline_item_id', $guidelineSet->id)
            ->where('year', 2030)
            ->where('category', 'BANGUNAN KANTOR')
            ->where('sub_category', 'Bangunan Kantor >= 5 lantai')
            ->where('building_type', 'KANTOR')
            ->whereNull('building_class')
            ->where('storey_min', 5)
            ->whereNull('storey_max')
            ->value('economic_life')
    )->toBe(50);
});

it('updates a building economic life record from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman BEL 2028',
        'year' => 2028,
        'description' => null,
        'is_active' => true,
    ]);
    $record = BuildingEconomicLife::query()->create([
        'guideline_item_id' => $guidelineSet->id,
        'year' => 2028,
        'category' => 'Apartemen',
        'sub_category' => 'High Rise',
        'building_type' => 'APARTEMEN',
        'building_class' => 'GRADE_A',
        'storey_min' => 6,
        'storey_max' => 20,
        'economic_life' => 45,
    ]);

    $this
        ->actingAs($admin)
        ->put(route('admin.ref-guidelines.building-economic-lives.update', $record), [
            'guideline_item_id' => $guidelineSet->id,
            'year' => 2028,
            'category' => 'Apartemen',
            'sub_category' => 'High Rise',
            'building_type' => 'APARTEMEN',
            'building_class' => 'GRADE_A',
            'storey_min' => 6,
            'storey_max' => 20,
            'economic_life' => 48,
        ])
        ->assertRedirect(route('admin.ref-guidelines.building-economic-lives.index'));

    $record->refresh();

    expect((int) $record->economic_life)->toBe(48);
});

it('deletes a building economic life record from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman BEL 2029',
        'year' => 2029,
        'description' => null,
        'is_active' => false,
    ]);
    $record = BuildingEconomicLife::query()->create([
        'guideline_item_id' => $guidelineSet->id,
        'year' => 2029,
        'category' => 'Gudang',
        'sub_category' => null,
        'building_type' => 'GUDANG',
        'building_class' => null,
        'storey_min' => null,
        'storey_max' => null,
        'economic_life' => 30,
    ]);

    $this
        ->actingAs($admin)
        ->delete(route('admin.ref-guidelines.building-economic-lives.destroy', $record))
        ->assertRedirect(route('admin.ref-guidelines.building-economic-lives.index'));

    expect(BuildingEconomicLife::query()->whereKey($record->id)->exists())->toBeFalse();
});

it('renders the ikk by province page in the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman IKK Provinsi 2026',
        'year' => 2026,
        'description' => null,
        'is_active' => true,
    ]);
    $province = Province::query()->create(['id' => '31', 'name' => 'DKI Jakarta']);
    $regency = Regency::query()->create(['id' => '3171', 'name' => 'Jakarta Selatan', 'province_id' => $province->id]);

    ConstructionCostIndex::query()->create([
        'guideline_set_id' => $guidelineSet->id,
        'year' => 2026,
        'region_code' => $regency->id,
        'region_name' => $regency->name,
        'ikk_value' => 1.1250,
    ]);

    $this
        ->actingAs($admin)
        ->get(route('admin.ref-guidelines.ikk-by-province.index', [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2026,
            'province_id' => $province->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/IkkByProvince/Index')
            ->where('items.0.region_code', '3171')
            ->where('items.0.ikk_value', 1.125));
});

it('saves ikk by province from the vue admin workspace', function () {
    $admin = createAdminUser();
    $guidelineSet = GuidelineSet::query()->create([
        'name' => 'Pedoman IKK Provinsi 2027',
        'year' => 2027,
        'description' => null,
        'is_active' => true,
    ]);
    $province = Province::query()->create(['id' => '32', 'name' => 'Jawa Barat']);
    $regencyA = Regency::query()->create(['id' => '3201', 'name' => 'Kabupaten Bogor', 'province_id' => $province->id]);
    $regencyB = Regency::query()->create(['id' => '3202', 'name' => 'Kabupaten Sukabumi', 'province_id' => $province->id]);

    $this
        ->actingAs($admin)
        ->post(route('admin.ref-guidelines.ikk-by-province.save'), [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2027,
            'province_id' => $province->id,
            'items' => [
                [
                    'region_code' => $regencyA->id,
                    'regency_name' => $regencyA->name,
                    'ikk_value' => 0.9785,
                ],
                [
                    'region_code' => $regencyB->id,
                    'regency_name' => $regencyB->name,
                    'ikk_value' => 1.0342,
                ],
            ],
        ])
        ->assertRedirect(route('admin.ref-guidelines.ikk-by-province.index', [
            'guideline_set_id' => $guidelineSet->id,
            'year' => 2027,
            'province_id' => $province->id,
        ]));

    expect((float) ConstructionCostIndex::query()
        ->where('guideline_set_id', $guidelineSet->id)
        ->where('year', 2027)
        ->where('region_code', $regencyA->id)
        ->value('ikk_value'))->toBe(0.9785);

    expect((float) ConstructionCostIndex::query()
        ->where('guideline_set_id', $guidelineSet->id)
        ->where('year', 2027)
        ->where('region_code', $regencyB->id)
        ->value('ikk_value'))->toBe(1.0342);
});
