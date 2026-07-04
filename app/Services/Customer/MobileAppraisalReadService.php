<?php

namespace App\Services\Customer;

use App\Enums\AppraisalStatusEnum;
use App\Enums\AssetTypeEnum;
use App\Enums\PurposeEnum;
use App\Enums\ReportTypeEnum;
use App\Enums\ValuationObjectiveEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Models\AppraisalUserConsent;
use App\Models\ConsentDocument;
use App\Models\Province;
use App\Models\User;
use App\Services\Customer\Payloads\AppraisalStatusTimelineBuilder;
use App\Support\AppraisalAssetFieldOptions;
use App\Support\Mobile\AppraisalStatusPresentation;

class MobileAppraisalReadService
{
    public function __construct(
        private readonly AppraisalStatusTimelineBuilder $timelineBuilder,
    ) {}

    public function paginate(User $user, array $filters): array
    {
        $base = AppraisalRequest::query()->whereBelongsTo($user);
        $counts = (clone $base)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => (int) $count);

        $query = $base
            ->withCount('assets')
            ->selectSub(
                AppraisalAsset::query()
                    ->select('address')
                    ->whereColumn('appraisal_assets.appraisal_request_id', 'appraisal_requests.id')
                    ->oldest('id')
                    ->limit(1),
                'first_asset_address',
            );

        if ($filters['q'] !== '') {
            $search = $filters['q'];
            $query->where(function ($query) use ($search): void {
                $query->where('request_number', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $query->orWhereKey((int) $search);
                }
            });
        }

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        return [
            'paginator' => $query
                ->latest('requested_at')
                ->latest('id')
                ->paginate($filters['per_page']),
            'stats' => [
                'total' => $counts->sum(),
                'by_status' => collect(AppraisalStatusEnum::cases())
                    ->mapWithKeys(static fn (AppraisalStatusEnum $status): array => [
                        $status->value => $counts->get($status->value, 0),
                    ])
                    ->all(),
            ],
            'filters' => $filters,
        ];
    }

    public function options(User $user): array
    {
        $maxFileUploads = (int) ini_get('max_file_uploads');
        $consent = ConsentDocument::query()
            ->published()
            ->forCode('appraisal_request_consent')
            ->latest('published_at')
            ->first();

        return [
            'asset_types' => $this->enumOptions(AssetTypeEnum::cases()),
            'purposes' => $this->enumOptions(PurposeEnum::cases()),
            'report_types' => $this->enumOptions(ReportTypeEnum::cases()),
            'valuation_objectives' => $this->enumOptions(ValuationObjectiveEnum::cases()),
            'statuses' => collect(AppraisalStatusEnum::cases())
                ->map(static fn (AppraisalStatusEnum $status): array => AppraisalStatusPresentation::make($status))
                ->values(),
            'provinces' => Province::query()->orderBy('name')->get(['id', 'name']),
            'asset_fields' => [
                'usage' => AppraisalAssetFieldOptions::usageOptions(),
                'title_document' => AppraisalAssetFieldOptions::titleDocumentOptions(),
                'land_shape' => AppraisalAssetFieldOptions::landShapeOptions(),
                'land_position' => AppraisalAssetFieldOptions::landPositionOptions(),
                'land_condition' => AppraisalAssetFieldOptions::landConditionOptions(),
                'topography' => AppraisalAssetFieldOptions::topographyOptions(),
            ],
            'upload_limits' => [
                'max_files' => $maxFileUploads > 0 ? $maxFileUploads : null,
                'max_file_size' => ini_get('upload_max_filesize'),
                'max_request_size' => ini_get('post_max_size'),
            ],
            'consent' => $consent ? [
                'document_id' => $consent->id,
                'version' => $consent->version,
                'hash' => $consent->hash,
                'title' => $consent->title,
                'sections' => $consent->sections,
                'checkbox_label' => $consent->checkbox_label,
                'accepted' => AppraisalUserConsent::query()
                    ->where('user_id', $user->id)
                    ->where('consent_document_id', $consent->id)
                    ->where('hash', $consent->hash)
                    ->exists(),
            ] : null,
        ];
    }

    public function detail(User $user, int $id): AppraisalRequest
    {
        return AppraisalRequest::query()
            ->whereBelongsTo($user)
            ->withCount('assets')
            ->with([
                'assets.province:id,name',
                'assets.regency:id,name',
                'assets.district:id,name',
                'assets.village:id,name',
                'latestCancellationRequest',
                'payments' => fn ($query) => $query->latest('id')->limit(1),
            ])
            ->findOrFail($id);
    }

    public function tracking(User $user, int $id): array
    {
        $record = AppraisalRequest::query()
            ->whereBelongsTo($user)
            ->withCount('assets')
            ->with([
                'cancelledBy:id,name',
                'physicalReportPrintedBy:id,name',
                'latestCancellationRequest.reviewedBy:id,name',
                'offerNegotiations:id,appraisal_request_id,user_id,action,round,offered_fee,expected_fee,selected_fee,reason,meta,created_at',
                'payments:id,appraisal_request_id,amount,method,gateway,status,paid_at,metadata,updated_at,created_at',
            ])
            ->findOrFail($id);

        return [
            'request' => $record,
            'timeline' => $this->timelineBuilder->build($record),
        ];
    }

    /** @param array<int, object> $cases */
    private function enumOptions(array $cases): array
    {
        return collect($cases)
            ->map(static fn (object $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ])
            ->values()
            ->all();
    }
}
