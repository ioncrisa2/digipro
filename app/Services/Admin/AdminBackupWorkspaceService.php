<?php

namespace App\Services\Admin;

use App\Models\AppraisalAssetFile;
use App\Models\AppraisalRequest;
use App\Models\AppraisalRequestFile;
use App\Models\AppraisalRequestRevisionItem;
use App\Services\Customer\Payloads\AppraisalPayloadFormatter;

class AdminBackupWorkspaceService
{
    public function __construct(
        private readonly AppraisalPayloadFormatter $formatter,
    ) {
    }

    public function indexPayload(array $filters, int $perPage): array
    {
        $customerRequestFileTypes = $this->formatter->customerRequestFileTypes();

        $records = AppraisalRequest::query()
            ->with('user:id,name')
            ->withCount('assets')
            ->withCount([
                'files as request_files_count' => fn ($query) => $query->whereIn('type', $customerRequestFileTypes),
                'assetFiles as asset_files_count',
                'revisionBatches as revision_batches_count',
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('request_number', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('client_name', 'like', '%' . $filters['q'] . '%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $filters['q'] . '%'));
                });
            })
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (AppraisalRequest $record) => $this->row($record));

        return [
            'filters' => $filters,
            'summary' => [
                'total_requests' => AppraisalRequest::query()->count(),
                'client_request_uploads' => AppraisalRequestFile::query()
                    ->whereIn('type', $customerRequestFileTypes)
                    ->count(),
                'asset_files' => AppraisalAssetFile::query()->count(),
                'revision_items' => AppraisalRequestRevisionItem::query()->count(),
            ],
            'records' => $this->paginatedRecordsPayload($records),
            'index_url' => route('admin.backups.index'),
            'restore' => [
                'url' => route('admin.backups.restore'),
                'max_upload_mb' => 100,
                'accept' => '.zip',
            ],
        ];
    }

    private function row(AppraisalRequest $record): array
    {
        return [
            'id' => (int) $record->id,
            'request_number' => (string) ($record->request_number ?: ('REQ-' . $record->id)),
            'client_name' => (string) ($record->client_name ?: '-'),
            'requester_name' => (string) ($record->user?->name ?: '-'),
            'status_label' => $record->status?->label() ?? '-',
            'status_value' => $record->status?->value ?? null,
            'assets_count' => (int) ($record->assets_count ?? 0),
            'request_files_count' => (int) ($record->request_files_count ?? 0),
            'asset_files_count' => (int) ($record->asset_files_count ?? 0),
            'revision_batches_count' => (int) ($record->revision_batches_count ?? 0),
            'requested_at' => $record->requested_at?->toIso8601String(),
            'updated_at' => $record->updated_at?->toIso8601String(),
            'download_url' => route('admin.backups.download', $record),
            'detail_url' => route('admin.appraisal-requests.show', $record),
        ];
    }

    private function paginatedRecordsPayload(object $records): array
    {
        return [
            'data' => $records->items(),
            'meta' => [
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'links' => $records->linkCollection()->toArray(),
            ],
        ];
    }
}
