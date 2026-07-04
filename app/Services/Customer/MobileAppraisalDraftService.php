<?php

namespace App\Services\Customer;

use App\Enums\AppraisalStatusEnum;
use App\Enums\AssetTypeEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetFile;
use App\Models\AppraisalRequest;
use App\Models\AppraisalUserConsent;
use App\Models\ConsentDocument;
use App\Models\District;
use App\Models\Regency;
use App\Models\User;
use App\Models\Village;
use App\Notifications\AppraisalRequestCreated;
use App\Services\Admin\AdminNotificationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileAppraisalDraftService
{
    public function __construct(
        private readonly GuidelineSetResolver $guidelineSetResolver,
        private readonly ReportDeliverySnapshotResolver $deliverySnapshotResolver,
        private readonly AdminNotificationService $adminNotificationService,
    ) {}

    public function create(User $user, array $data): AppraisalRequest
    {
        $draft = AppraisalRequest::query()->create([
            'user_id' => $user->id,
            'purpose' => $data['purpose'] ?? 'jual_beli',
            'valuation_objective' => 'kajian_nilai_pasar_dalam_bentuk_range',
            'status' => AppraisalStatusEnum::Draft,
            'client_name' => $data['client_name'] ?? $user->name,
            'client_address' => $data['client_address'] ?? null,
            'client_spk_number' => $data['client_spk_number'] ?? null,
            'user_request_note' => $data['user_request_note'] ?? null,
            'report_type' => $data['report_type'] ?? 'terinci',
            'report_format' => 'both',
            'physical_copies_count' => 1,
            'sertifikat_on_hand_confirmed' => (bool) ($data['sertifikat_on_hand_confirmed'] ?? false),
            'certificate_not_encumbered_confirmed' => (bool) ($data['certificate_not_encumbered_confirmed'] ?? false),
        ]);

        return $this->loadDraft($draft);
    }

    public function find(User $user, int $draftId): AppraisalRequest
    {
        return $this->loadDraft($this->ownedDraftQuery($user, $draftId)->firstOrFail());
    }

    public function update(User $user, int $draftId, array $data): AppraisalRequest
    {
        $draft = $this->ownedDraftQuery($user, $draftId)->firstOrFail();
        $draft->update($data);

        return $this->loadDraft($draft);
    }

    public function addAsset(User $user, int $draftId, array $data): AppraisalRequest
    {
        $draft = $this->ownedDraftQuery($user, $draftId)->firstOrFail();
        AppraisalAsset::query()->create($this->assetAttributes($data, $draft->id));

        return $this->loadDraft($draft);
    }

    public function updateAsset(User $user, int $draftId, int $assetId, array $data): AppraisalRequest
    {
        $draft = $this->ownedDraftQuery($user, $draftId)->firstOrFail();
        $asset = $this->ownedAssetQuery($draft, $assetId)->firstOrFail();
        $asset->update($this->assetAttributes($data, $draft->id, false));

        return $this->loadDraft($draft);
    }

    public function deleteAsset(User $user, int $draftId, int $assetId): AppraisalRequest
    {
        $draft = $this->ownedDraftQuery($user, $draftId)->firstOrFail();
        $asset = $this->ownedAssetQuery($draft, $assetId)->with('files:id,appraisal_asset_id,path')->firstOrFail();
        $paths = $asset->files->pluck('path')->filter()->all();

        $asset->delete();
        Storage::disk('public')->delete($paths);

        return $this->loadDraft($draft);
    }

    public function uploadFiles(
        User $user,
        int $draftId,
        int $assetId,
        string $type,
        array $files,
    ): Collection {
        $draft = $this->ownedDraftQuery($user, $draftId)->firstOrFail();
        $asset = $this->ownedAssetQuery($draft, $assetId)->firstOrFail();
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($draft, $asset, $type, $files, &$storedPaths): Collection {
                $records = new Collection;

                foreach ($files as $file) {
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    $path = $file->storeAs(
                        "appraisal-requests/{$draft->id}/assets/{$asset->id}/{$this->fileDirectory($type)}",
                        Str::uuid().'.'.($file->getClientOriginalExtension() ?: 'bin'),
                        'public',
                    );
                    $storedPaths[] = $path;

                    $records->push(AppraisalAssetFile::query()->create([
                        'appraisal_asset_id' => $asset->id,
                        'type' => $type,
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]));
                }

                return $records;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            throw $exception;
        }
    }

    public function deleteFile(User $user, int $draftId, int $fileId): void
    {
        $draft = $this->ownedDraftQuery($user, $draftId)->firstOrFail();
        $file = AppraisalAssetFile::query()
            ->whereKey($fileId)
            ->whereHas('appraisalAsset', fn ($query) => $query->where('appraisal_request_id', $draft->id))
            ->firstOrFail();
        $path = $file->path;

        $file->delete();
        Storage::disk('public')->delete($path);
    }

    public function acceptConsent(User $user, Request $request, int $documentId, string $hash): AppraisalUserConsent
    {
        $document = $this->latestConsent();

        if (! $document || $document->id !== $documentId || ! hash_equals($document->hash, $hash)) {
            throw ValidationException::withMessages([
                'document_id' => ['Dokumen persetujuan sudah berubah. Muat ulang persetujuan terbaru.'],
            ]);
        }

        return AppraisalUserConsent::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'consent_document_id' => $document->id,
            ],
            [
                'code' => $document->code,
                'version' => $document->version,
                'hash' => $document->hash,
                'accepted_at' => now(),
                'ip' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            ],
        );
    }

    public function latestConsent(): ?ConsentDocument
    {
        return ConsentDocument::query()
            ->published()
            ->forCode('appraisal_request_consent')
            ->latest('published_at')
            ->first();
    }

    public function submit(User $user, Request $request, int $draftId): AppraisalRequest
    {
        $submitted = DB::transaction(function () use ($user, $request, $draftId): AppraisalRequest {
            $draft = $this->ownedDraftQuery($user, $draftId)
                ->lockForUpdate()
                ->with(['assets.files'])
                ->firstOrFail();

            $this->validateForSubmission($draft, $user);

            $consentDocument = $this->latestConsent();
            $consent = $consentDocument
                ? AppraisalUserConsent::query()
                    ->where('user_id', $user->id)
                    ->where('consent_document_id', $consentDocument->id)
                    ->where('hash', $consentDocument->hash)
                    ->first()
                : null;

            if (! $consent) {
                throw ValidationException::withMessages([
                    'consent' => ['Persetujuan terbaru wajib diterima sebelum permohonan dikirim.'],
                ]);
            }

            $guidelineSetId = $this->guidelineSetResolver->resolveId();

            if (! $guidelineSetId) {
                throw ValidationException::withMessages([
                    'guideline_set_id' => ['Guideline acuan belum tersedia.'],
                ]);
            }

            $delivery = $this->deliverySnapshotResolver->resolve($user, true);
            $draft->update([
                'guideline_set_id' => $guidelineSetId,
                'report_format' => 'both',
                'physical_copies_count' => 1,
                'report_delivery_address' => $delivery['address'],
                'report_delivery_recipient_name' => $delivery['recipient_name'],
                'report_delivery_recipient_phone' => $delivery['recipient_phone'],
                'consent_accepted_at' => $consent->accepted_at,
                'consent_version' => $consent->version,
                'consent_hash' => $consent->hash,
                'consent_ip' => $consent->ip,
                'consent_user_agent' => $consent->user_agent,
                'certificate_statements_accepted_at' => now(),
                'certificate_statement_ip' => $request->ip(),
                'certificate_statement_user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                'requested_at' => now(),
                'status' => AppraisalStatusEnum::Submitted,
            ]);

            return $draft->refresh();
        });

        $user->notify(new AppraisalRequestCreated($submitted->id, $submitted->request_number));
        $this->adminNotificationService->notifyAdmins(
            'Permohonan penilaian baru',
            ($submitted->request_number ?? "#{$submitted->id}")." dibuat oleh {$user->name}.",
            route('admin.appraisal-requests.show', ['appraisalRequest' => $submitted->id]),
            'heroicon-o-clipboard-document-check',
            $user->id,
        );

        return $submitted;
    }

    private function validateForSubmission(AppraisalRequest $draft, User $user): void
    {
        if (! filled($user->phone_number)
            || ! filled($user->billing_recipient_name)
            || ! filled($user->billing_address_detail)) {
            throw ValidationException::withMessages([
                'profile' => ['Lengkapi nomor telepon, penerima billing, dan alamat billing sebelum submit.'],
            ]);
        }

        $payload = [
            'purpose' => $draft->purpose?->value,
            'report_type' => $draft->report_type?->value,
            'sertifikat_on_hand_confirmed' => $draft->sertifikat_on_hand_confirmed,
            'certificate_not_encumbered_confirmed' => $draft->certificate_not_encumbered_confirmed,
            'assets' => $draft->assets->map(fn (AppraisalAsset $asset): array => [
                ...$asset->only([
                    'asset_type',
                    'land_area',
                    'building_area',
                    'building_floors',
                    'build_year',
                    'title_document',
                    'province_id',
                    'regency_id',
                    'district_id',
                    'village_id',
                    'address',
                    'maps_link',
                    'coordinates_lat',
                    'coordinates_lng',
                ]),
                'file_counts' => $asset->files->countBy('type')->all(),
            ])->all(),
        ];

        $validator = Validator::make($payload, [
            'purpose' => ['required', Rule::in(['jual_beli', 'penjaminan_utang', 'lelang'])],
            'report_type' => ['required', Rule::in(['terinci', 'singkat'])],
            'sertifikat_on_hand_confirmed' => ['accepted'],
            'certificate_not_encumbered_confirmed' => ['accepted'],
            'assets' => ['required', 'array', 'min:1'],
            'assets.*.asset_type' => ['required', Rule::in(['tanah', 'tanah_bangunan'])],
            'assets.*.land_area' => ['required', 'numeric', 'min:0'],
            'assets.*.title_document' => ['required', 'string'],
            'assets.*.province_id' => ['required', 'exists:provinces,id'],
            'assets.*.regency_id' => ['required', 'exists:regencies,id'],
            'assets.*.district_id' => ['required', 'exists:districts,id'],
            'assets.*.address' => ['required', 'string'],
        ]);

        $validator->after(function ($validator) use ($payload): void {
            foreach ($payload['assets'] as $index => $asset) {
                $hasCoordinates = is_numeric($asset['coordinates_lat']) && is_numeric($asset['coordinates_lng']);

                if (! $hasCoordinates && ! filled($asset['maps_link'])) {
                    $validator->errors()->add("assets.{$index}.location", 'Koordinat atau link Google Maps wajib diisi.');
                }

                if ($asset['asset_type'] !== AssetTypeEnum::TANAH->value) {
                    foreach (['building_area', 'building_floors', 'build_year'] as $field) {
                        if (! is_numeric($asset[$field])) {
                            $validator->errors()->add("assets.{$index}.{$field}", 'Field bangunan ini wajib diisi.');
                        }
                    }
                }

                $this->validateLocationHierarchy($validator, $asset, $index);
                $this->validateRequiredFiles($validator, $asset, $index);
            }
        });

        $validator->validate();
    }

    private function validateLocationHierarchy($validator, array $asset, int $index): void
    {
        if (filled($asset['regency_id']) && ! Regency::query()
            ->whereKey($asset['regency_id'])
            ->where('province_id', $asset['province_id'])
            ->exists()) {
            $validator->errors()->add("assets.{$index}.regency_id", 'Kabupaten/kota tidak sesuai provinsi.');
        }

        if (filled($asset['district_id']) && ! District::query()
            ->whereKey($asset['district_id'])
            ->where('regency_id', $asset['regency_id'])
            ->exists()) {
            $validator->errors()->add("assets.{$index}.district_id", 'Kecamatan tidak sesuai kabupaten/kota.');
        }

        if (filled($asset['village_id']) && ! Village::query()
            ->whereKey($asset['village_id'])
            ->where('district_id', $asset['district_id'])
            ->exists()) {
            $validator->errors()->add("assets.{$index}.village_id", 'Kelurahan/desa tidak sesuai kecamatan.');
        }
    }

    private function validateRequiredFiles($validator, array $asset, int $index): void
    {
        $counts = $asset['file_counts'];
        $requirements = [
            'doc_certs' => [1, null, 'Sertifikat tanah wajib diunggah.'],
            'doc_pbb' => [1, null, 'PBB terbaru wajib diunggah.'],
            'photo_access_road' => [1, 5, 'Foto akses jalan wajib diunggah.'],
            'photo_front' => [1, 5, 'Foto tampak depan wajib diunggah.'],
            'photo_interior' => [1, 20, 'Foto interior wajib diunggah.'],
        ];

        if ($asset['asset_type'] !== AssetTypeEnum::TANAH->value) {
            $requirements['doc_imb'] = [1, null, 'IMB/PBG wajib diunggah untuk aset dengan bangunan.'];
        }

        foreach ($requirements as $type => [$minimum, $maximum, $message]) {
            $count = (int) ($counts[$type] ?? 0);

            if ($count < $minimum) {
                $validator->errors()->add("assets.{$index}.files.{$type}", $message);
            }

            if ($maximum !== null && $count > $maximum) {
                $validator->errors()->add("assets.{$index}.files.{$type}", "Jumlah file {$type} melebihi batas {$maximum}.");
            }
        }
    }

    private function ownedDraftQuery(User $user, int $draftId)
    {
        return AppraisalRequest::query()
            ->whereBelongsTo($user)
            ->whereKey($draftId)
            ->where('status', AppraisalStatusEnum::Draft->value);
    }

    private function ownedAssetQuery(AppraisalRequest $draft, int $assetId)
    {
        return $draft->assets()->whereKey($assetId);
    }

    private function loadDraft(AppraisalRequest $draft): AppraisalRequest
    {
        return $draft->fresh([
            'assets' => fn ($query) => $query->oldest('id'),
            'assets.files' => fn ($query) => $query->oldest('id'),
        ])->loadCount('assets');
    }

    private function assetAttributes(array $data, int $draftId, bool $includeRequestId = true): array
    {
        $attributes = [];

        if ($includeRequestId) {
            $attributes['appraisal_request_id'] = $draftId;
        }

        foreach ([
            'peruntukan',
            'title_document',
            'land_shape',
            'land_position',
            'land_condition',
            'topography',
            'province_id',
            'regency_id',
            'district_id',
            'village_id',
            'address',
            'maps_link',
            'coordinates_lat',
            'coordinates_lng',
            'land_area',
            'building_area',
            'building_floors',
            'build_year',
            'renovation_year',
            'frontage_width',
            'access_road_width',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if (array_key_exists('asset_type', $data)) {
            $attributes['asset_type'] = $data['asset_type'] === AssetTypeEnum::TANAH->value
                ? AssetTypeEnum::TANAH->value
                : AssetTypeEnum::TANAH_BANGUNAN->value;
        }

        return $attributes;
    }

    private function fileDirectory(string $type): string
    {
        return str_starts_with($type, 'photo_')
            ? 'photos/'.Str::after($type, 'photo_')
            : 'documents/'.Str::after($type, 'doc_');
    }
}
