<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppraisalStatusEnum;
use App\Enums\AssetTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use App\Support\AppraisalAssetFieldOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Response;

class AdminController extends Controller
{
    public function dashboard(): Response
    {
        $stats = [
            [
                'key' => 'submitted',
                'label' => 'Submitted',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::Submitted)->count(),
                'description' => 'Menunggu verifikasi',
                'tone' => 'info',
            ],
            [
                'key' => 'docs_incomplete',
                'label' => 'Dokumen Kurang',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::DocsIncomplete)->count(),
                'description' => 'Perlu tindak lanjut',
                'tone' => 'warning',
            ],
            [
                'key' => 'waiting_offer',
                'label' => 'Waiting Offer',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::WaitingOffer)->count(),
                'description' => 'Siap diberi penawaran',
                'tone' => 'warning',
            ],
            [
                'key' => 'offer_sent',
                'label' => 'Offer Sent',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::OfferSent)->count(),
                'description' => 'Menunggu respons klien',
                'tone' => 'primary',
            ],
            [
                'key' => 'waiting_signature',
                'label' => 'Waiting Signature',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::WaitingSignature)->count(),
                'description' => 'Kontrak belum ditandatangani',
                'tone' => 'warning',
            ],
            [
                'key' => 'contract_signed',
                'label' => 'Contract Signed',
                'value' => AppraisalRequest::query()->where('status', AppraisalStatusEnum::ContractSigned)->count(),
                'description' => 'Siap proses valuasi',
                'tone' => 'success',
            ],
            [
                'key' => 'requests_today',
                'label' => 'Permohonan Hari Ini',
                'value' => AppraisalRequest::query()->whereDate('requested_at', now()->toDateString())->count(),
                'description' => 'Permohonan baru',
                'tone' => 'success',
            ],
            [
                'key' => 'assets_today',
                'label' => 'Aset Hari Ini',
                'value' => AppraisalAsset::query()->whereDate('created_at', now()->toDateString())->count(),
                'description' => 'Aset baru diunggah',
                'tone' => 'info',
            ],
        ];

        $actionItems = AppraisalRequest::query()
            ->whereIn('status', [
                AppraisalStatusEnum::Submitted,
                AppraisalStatusEnum::DocsIncomplete,
                AppraisalStatusEnum::Verified,
                AppraisalStatusEnum::WaitingOffer,
            ])
            ->with('user')
            ->withCount('assets')
            ->latest('requested_at')
            ->limit(8)
            ->get()
            ->map(fn (AppraisalRequest $record) => $this->transformRequestListItem($record))
            ->values();

        $paymentQueue = AppraisalRequest::query()
            ->where('status', AppraisalStatusEnum::ContractSigned)
            ->with('user')
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (AppraisalRequest $record) => [
                'id' => $record->id,
                'request_number' => $record->request_number ?? ('REQ-' . $record->id),
                'requester_name' => $record->user?->name ?? '-',
                'fee_total' => (int) ($record->fee_total ?? 0),
                'offer_validity_days' => $record->offer_validity_days,
                'updated_at' => $record->updated_at?->toIso8601String(),
                'show_url' => route('admin.appraisal-requests.show', $record),
                'legacy_url' => $this->legacyAppraisalRequestUrl($record),
            ])
            ->values();

        return inertia('Admin/Dashboard', [
            'stats' => $stats,
            'actionItems' => $actionItems,
            'paymentQueue' => $paymentQueue,
            'modules' => $this->moduleCards(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function appraisalRequestsIndex(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', 'all'),
        ];

        $records = AppraisalRequest::query()
            ->with('user')
            ->withCount('assets')
            ->withCount([
                'offerNegotiations as negotiation_rounds_used' => fn ($query) => $query->where('action', 'counter_request'),
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $query->where(function ($innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('request_number', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('client_name', 'like', '%' . $filters['q'] . '%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $filters['q'] . '%'));
                });
            })
            ->when($filters['status'] !== 'all', fn ($query) => $query->where('status', $filters['status']))
            ->latest('requested_at')
            ->paginate(15)
            ->withQueryString();

        $records->through(fn (AppraisalRequest $record) => $this->transformRequestTableRow($record));

        return inertia('Admin/AppraisalRequests/Index', [
            'filters' => $filters,
            'statusOptions' => array_map(
                fn (AppraisalStatusEnum $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                AppraisalStatusEnum::cases()
            ),
            'summary' => [
                'total' => AppraisalRequest::query()->count(),
                'needs_action' => AppraisalRequest::query()
                    ->whereIn('status', [
                        AppraisalStatusEnum::Submitted,
                        AppraisalStatusEnum::DocsIncomplete,
                        AppraisalStatusEnum::Verified,
                        AppraisalStatusEnum::WaitingOffer,
                    ])
                    ->count(),
                'payment_pending' => AppraisalRequest::query()
                    ->where('status', AppraisalStatusEnum::ContractSigned)
                    ->count(),
            ],
            'records' => [
                'data' => $records->items(),
                'meta' => [
                    'from' => $records->firstItem(),
                    'to' => $records->lastItem(),
                    'total' => $records->total(),
                    'links' => $records->linkCollection()->toArray(),
                ],
            ],
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function appraisalRequestsShow(AppraisalRequest $appraisalRequest): Response
    {
        $appraisalRequest->load([
            'guidelineSet',
            'user',
            'files',
            'assets.files',
            'payments' => fn ($query) => $query->latest('id'),
            'offerNegotiations' => fn ($query) => $query->with('user')->latest('id'),
        ]);

        $locationMaps = $this->buildLocationMaps($appraisalRequest);
        $latestCounterRequest = $appraisalRequest->offerNegotiations
            ->first(fn ($entry) => $entry->action === 'counter_request');

        return inertia('Admin/AppraisalRequests/Show', [
            'record' => [
                'id' => $appraisalRequest->id,
                'request_number' => $appraisalRequest->request_number ?? ('REQ-' . $appraisalRequest->id),
                'purpose_label' => $appraisalRequest->purpose?->label() ?? '-',
                'status_label' => $appraisalRequest->status?->label() ?? '-',
                'status_value' => $appraisalRequest->status?->value ?? null,
                'contract_status_label' => $appraisalRequest->contract_status?->label() ?? '-',
                'contract_status_value' => $appraisalRequest->contract_status?->value ?? null,
                'report_type_label' => $appraisalRequest->report_type?->label() ?? '-',
                'requested_at' => $appraisalRequest->requested_at?->toIso8601String(),
                'verified_at' => $appraisalRequest->verified_at?->toIso8601String(),
                'client_name' => $appraisalRequest->client_name ?: '-',
                'contract_number' => $appraisalRequest->contract_number ?: '-',
                'contract_date' => $appraisalRequest->contract_date?->toIso8601String(),
                'valuation_duration_days' => $appraisalRequest->valuation_duration_days,
                'offer_validity_days' => $appraisalRequest->offer_validity_days,
                'fee_total' => (int) ($appraisalRequest->fee_total ?? 0),
                'fee_has_dp' => (bool) $appraisalRequest->fee_has_dp,
                'fee_dp_percent' => $appraisalRequest->fee_dp_percent,
                'latest_expected_fee' => $latestCounterRequest?->expected_fee,
                'latest_negotiation_reason' => $latestCounterRequest?->reason,
                'notes' => $appraisalRequest->notes,
                'user_request_note' => $appraisalRequest->user_request_note,
                'guideline_set' => $appraisalRequest->guidelineSet?->name ?? '-',
                'legacy_url' => $this->legacyAppraisalRequestUrl($appraisalRequest),
            ],
            'requester' => [
                'id' => $appraisalRequest->user?->id,
                'name' => $appraisalRequest->user?->name ?? '-',
                'email' => $appraisalRequest->user?->email ?? '-',
            ],
            'requestFiles' => $appraisalRequest->files
                ->map(fn ($file) => $this->transformRequestFile($file))
                ->values(),
            'assets' => $appraisalRequest->assets
                ->sortBy('id')
                ->values()
                ->map(fn ($asset, $index) => $this->transformAsset($asset, $index + 1, $locationMaps))
                ->values(),
            'payments' => $appraisalRequest->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'amount' => (int) $payment->amount,
                'method_label' => $payment->method === 'gateway' ? 'Gateway' : 'Manual',
                'status' => $payment->status,
                'gateway' => $payment->gateway,
                'external_payment_id' => $payment->external_payment_id,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ])->values(),
            'negotiations' => $appraisalRequest->offerNegotiations->map(fn ($negotiation) => [
                'id' => $negotiation->id,
                'action_label' => $this->formatNegotiationAction($negotiation->action),
                'actor_name' => $negotiation->user?->name ?? 'System',
                'round' => $negotiation->round,
                'offered_fee' => $negotiation->offered_fee,
                'expected_fee' => $negotiation->expected_fee,
                'selected_fee' => $negotiation->selected_fee,
                'reason' => $negotiation->reason,
                'created_at' => $negotiation->created_at?->toIso8601String(),
            ])->values(),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    public function moduleShow(string $module): Response
    {
        $definition = $this->moduleDefinitions()[$module] ?? null;

        abort_if($definition === null, 404);

        return inertia('Admin/Modules/Show', [
            'module' => array_merge($definition, [
                'slug' => $module,
                'status_label' => $this->moduleStatusLabel($definition['status']),
            ]),
            'legacyPanelUrl' => url('/legacy-admin'),
        ]);
    }

    private function transformRequestListItem(AppraisalRequest $record): array
    {
        return [
            'id' => $record->id,
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'requester_name' => $record->user?->name ?? '-',
            'client_name' => $record->client_name ?: ($record->user?->name ?? '-'),
            'status_label' => $record->status?->label() ?? '-',
            'status_value' => $record->status?->value ?? null,
            'assets_count' => (int) ($record->assets_count ?? 0),
            'requested_at' => $record->requested_at?->toIso8601String(),
            'show_url' => route('admin.appraisal-requests.show', $record),
            'legacy_url' => $this->legacyAppraisalRequestUrl($record),
        ];
    }

    private function transformRequestTableRow(AppraisalRequest $record): array
    {
        return [
            'id' => $record->id,
            'request_number' => $record->request_number ?? ('REQ-' . $record->id),
            'requester_name' => $record->user?->name ?? '-',
            'client_name' => $record->client_name ?: ($record->user?->name ?? '-'),
            'status_label' => $record->status?->label() ?? '-',
            'status_value' => $record->status?->value ?? null,
            'contract_status_label' => $record->contract_status?->label() ?? '-',
            'contract_status_value' => $record->contract_status?->value ?? null,
            'assets_count' => (int) ($record->assets_count ?? 0),
            'negotiation_rounds_used' => (int) ($record->negotiation_rounds_used ?? 0),
            'fee_total' => (int) ($record->fee_total ?? 0),
            'requested_at' => $record->requested_at?->toIso8601String(),
            'show_url' => route('admin.appraisal-requests.show', $record),
            'legacy_url' => $this->legacyAppraisalRequestUrl($record),
        ];
    }

    private function legacyAppraisalRequestUrl(AppraisalRequest $record): ?string
    {
        try {
            return route('filament.admin.resources.appraisal-requests.view', ['record' => $record]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatNegotiationAction(?string $action): string
    {
        return match ($action) {
            'offer_sent' => 'Penawaran dikirim',
            'offer_revised' => 'Counter offer dikirim',
            'counter_request' => 'Pengajuan negosiasi',
            'selected' => 'Fee dipilih',
            'accept_offer' => 'Penawaran diterima',
            'accepted' => 'Penawaran diterima',
            'contract_sign_mock' => 'Tanda tangan kontrak',
            'cancel_request' => 'Permohonan dibatalkan',
            'cancelled' => 'Negosiasi dibatalkan',
            default => Arr::headline((string) $action),
        };
    }

    private function buildLocationMaps(AppraisalRequest $appraisalRequest): array
    {
        $provinceIds = $appraisalRequest->assets->pluck('province_id')->filter()->unique()->values();
        $regencyIds = $appraisalRequest->assets->pluck('regency_id')->filter()->unique()->values();
        $districtIds = $appraisalRequest->assets->pluck('district_id')->filter()->unique()->values();
        $villageIds = $appraisalRequest->assets->pluck('village_id')->filter()->unique()->values();

        return [
            'province' => Province::query()->whereIn('id', $provinceIds)->pluck('name', 'id')->all(),
            'regency' => Regency::query()->whereIn('id', $regencyIds)->pluck('name', 'id')->all(),
            'district' => District::query()->whereIn('id', $districtIds)->pluck('name', 'id')->all(),
            'village' => Village::query()->whereIn('id', $villageIds)->pluck('name', 'id')->all(),
        ];
    }

    private function transformRequestFile(object $file): array
    {
        return [
            'id' => $file->id,
            'type' => (string) $file->type,
            'type_label' => $this->requestFileTypeLabel($file->type),
            'original_name' => $file->original_name ?: basename((string) $file->path),
            'mime' => $file->mime,
            'size' => (int) ($file->size ?? 0),
            'size_label' => $this->formatBytes($file->size),
            'url' => Storage::disk('public')->url($file->path),
            'created_at' => $file->created_at?->toIso8601String(),
        ];
    }

    private function transformAsset(AppraisalAsset $asset, int $order, array $locationMaps): array
    {
        $files = $asset->files->sortByDesc('created_at')->values();

        return [
            'id' => $asset->id,
            'order' => $order,
            'asset_code' => $asset->asset_code,
            'address' => $asset->address ?: 'Alamat belum diisi',
            'asset_type' => $asset->asset_type ?: '-',
            'asset_type_label' => AssetTypeEnum::tryFrom((string) $asset->asset_type)?->label() ?? ($asset->asset_type ?: '-'),
            'peruntukan' => $asset->peruntukan,
            'peruntukan_label' => $this->assetOptionLabel('usage', $asset->peruntukan),
            'title_document_label' => $this->assetOptionLabel('title_document', $asset->title_document),
            'land_shape_label' => $this->assetOptionLabel('land_shape', $asset->land_shape),
            'land_position_label' => $this->assetOptionLabel('land_position', $asset->land_position),
            'land_condition_label' => $this->assetOptionLabel('land_condition', $asset->land_condition),
            'topography_label' => $this->assetOptionLabel('topography', $asset->topography),
            'province_name' => $locationMaps['province'][$asset->province_id] ?? null,
            'regency_name' => $locationMaps['regency'][$asset->regency_id] ?? null,
            'district_name' => $locationMaps['district'][$asset->district_id] ?? null,
            'village_name' => $locationMaps['village'][$asset->village_id] ?? null,
            'maps_link' => $asset->maps_link,
            'coordinates_lat' => $asset->coordinates_lat,
            'coordinates_lng' => $asset->coordinates_lng,
            'land_area' => $asset->land_area,
            'building_area' => $asset->building_area,
            'building_floors' => $asset->building_floors,
            'build_year' => $asset->build_year,
            'renovation_year' => $asset->renovation_year,
            'frontage_width' => $asset->frontage_width,
            'access_road_width' => $asset->access_road_width,
            'land_value_final' => $asset->land_value_final,
            'building_value_final' => $asset->building_value_final,
            'market_value_final' => $asset->market_value_final,
            'estimated_value_low' => $asset->estimated_value_low,
            'estimated_value_high' => $asset->estimated_value_high,
            'documents' => $files
                ->whereIn('type', ['doc_pbb', 'doc_imb', 'doc_certs'])
                ->map(fn ($file) => $this->transformAssetFile($file))
                ->values(),
            'photos' => $files
                ->whereIn('type', ['photo_access_road', 'photo_front', 'photo_interior'])
                ->map(fn ($file) => $this->transformAssetFile($file))
                ->values(),
        ];
    }

    private function transformAssetFile(object $file): array
    {
        return [
            'id' => $file->id,
            'type' => (string) $file->type,
            'type_label' => $this->assetFileTypeLabel($file->type),
            'original_name' => $file->original_name ?: basename((string) $file->path),
            'mime' => $file->mime,
            'size' => (int) ($file->size ?? 0),
            'size_label' => $this->formatBytes($file->size),
            'url' => Storage::disk('public')->url($file->path),
            'created_at' => $file->created_at?->toIso8601String(),
        ];
    }

    private function assetOptionLabel(string $group, ?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $options = match ($group) {
            'usage' => AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::usageOptions()),
            'title_document' => AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::titleDocumentOptions()),
            'land_shape' => AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::landShapeOptions()),
            'land_position' => AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::landPositionOptions()),
            'land_condition' => AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::landConditionOptions()),
            'topography' => AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::topographyOptions()),
            default => [],
        };

        return $options[$value] ?? Arr::headline($value);
    }

    private function requestFileTypeLabel(?string $type): string
    {
        return match ((string) $type) {
            'contract_signed_pdf' => 'PDF Kontrak Ditandatangani',
            default => Arr::headline((string) $type),
        };
    }

    private function assetFileTypeLabel(?string $type): string
    {
        return match ((string) $type) {
            'doc_pbb' => 'PBB',
            'doc_imb' => 'IMB / PBG',
            'doc_certs' => 'Sertifikat',
            'photo_access_road' => 'Foto Akses Jalan',
            'photo_front' => 'Foto Depan',
            'photo_interior' => 'Foto Dalam',
            default => Arr::headline((string) $type),
        };
    }

    private function formatBytes(mixed $bytes): string
    {
        if (! is_numeric($bytes) || (float) $bytes <= 0) {
            return '0 B';
        }

        $number = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = (int) floor(log($number, 1024));
        $index = min($index, count($units) - 1);
        $value = $number / (1024 ** $index);

        return sprintf('%s %s', number_format($value, $index === 0 ? 0 : 2), $units[$index]);
    }

    private function moduleCards(): array
    {
        $cards = [];

        foreach ($this->moduleDefinitions() as $slug => $definition) {
            $cards[] = [
                'slug' => $slug,
                'title' => $definition['title'],
                'description' => $definition['description'],
                'resource_count' => count($definition['legacy_resources']),
                'status' => $definition['status'],
                'status_label' => $this->moduleStatusLabel($definition['status']),
                'show_url' => route('admin.modules.show', ['module' => $slug]),
            ];
        }

        return $cards;
    }

    private function moduleStatusLabel(string $status): string
    {
        return match ($status) {
            'in_progress' => 'Sedang dimigrasikan',
            'planned' => 'Belum dimigrasikan',
            'bridge' => 'Butuh jembatan backend',
            default => 'Legacy',
        };
    }

    private function moduleDefinitions(): array
    {
        return [
            'payments' => [
                'title' => 'Keuangan',
                'description' => 'Menggantikan PaymentResource dan OfficeBankAccountResource berikut alur verifikasi pembayaran.',
                'status' => 'bridge',
                'legacy_resources' => [
                    'PaymentResource',
                    'OfficeBankAccountResource',
                ],
                'dependencies' => [
                    'PaymentController masih mengirim database notification dengan builder Filament.',
                    'Verifikasi pembayaran di legacy admin masih menjadi sumber kebenaran untuk beberapa aksi.',
                ],
            ],
            'content' => [
                'title' => 'Konten',
                'description' => 'Migrasi artikel, kategori artikel, dan tag ke halaman Vue dengan editor yang bisa diganti.',
                'status' => 'planned',
                'legacy_resources' => [
                    'ArticleResource',
                    'ArticleCategoryResource',
                    'TagResource',
                ],
                'dependencies' => [
                    'ArticleController publik sudah Inertia, tetapi CMS penulisannya masih Filament.',
                ],
            ],
            'legal-content' => [
                'title' => 'Konten & Legal',
                'description' => 'Dokumen legal, FAQ, feature highlight, testimonial, dan log persetujuan pengguna.',
                'status' => 'planned',
                'legacy_resources' => [
                    'ConsentDocumentResource',
                    'TermsDocumentResource',
                    'PrivacyPolicyResource',
                    'FaqResource',
                    'FeatureResource',
                    'TestimonialResource',
                    'AppraisalUserConsentResource',
                ],
                'dependencies' => [
                    'Ada editor Tiptap khusus Filament pada resource legal tertentu.',
                ],
            ],
            'communications' => [
                'title' => 'Komunikasi',
                'description' => 'Inbox pesan kontak dari landing page dan audit tindak lanjutnya.',
                'status' => 'planned',
                'legacy_resources' => [
                    'ContactMessageResource',
                ],
                'dependencies' => [
                    'LandingController sudah menyimpan pesan, admin inbox masih hanya tersedia di Filament.',
                ],
            ],
            'master-data' => [
                'title' => 'Master Data',
                'description' => 'User terdaftar dan daftar nama lokasi yang dipakai lintas flow penilaian.',
                'status' => 'planned',
                'legacy_resources' => [
                    'UserResource',
                    'ProvinceResource',
                    'RegencyResource',
                    'DistrictResource',
                    'VillageResource',
                ],
                'dependencies' => [
                    'Cluster DaftarNamaLokasi masih murni CRUD Filament.',
                ],
            ],
            'ref-guidelines' => [
                'title' => 'Ref Guidelines',
                'description' => 'Seluruh referensi appraisal, termasuk guideline set, cost element, index, dan page IKK per provinsi.',
                'status' => 'planned',
                'legacy_resources' => [
                    'RefGuidelineSetResource',
                    'BuildingEconomicLifeResource',
                    'ConstructionCostIndexResource',
                    'CostElementResource',
                    'FloorIndexResource',
                    'MappiRcnStandardResource',
                    'ValuationSettingResource',
                    'IkkByProvince Page',
                ],
                'dependencies' => [
                    'Ada custom page Filament dengan repeater dan transaction save mass update.',
                ],
            ],
            'access-control' => [
                'title' => 'Hak Akses',
                'description' => 'Migrasi RoleResource dan ketergantungan ke filament-shield untuk admin policy management.',
                'status' => 'bridge',
                'legacy_resources' => [
                    'RoleResource',
                ],
                'dependencies' => [
                    'User model masih memakai spatie/permission dan konfigurasi super_admin dari filament-shield.',
                    'Policy Role saat ini dihasilkan oleh shield dan belum dipindah ke UI Vue.',
                ],
            ],
        ];
    }
}
