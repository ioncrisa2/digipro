<?php

namespace App\Services;

use App\Enums\AppraisalStatusEnum;
use App\Enums\AssetTypeEnum;
use App\Enums\ValuationObjectiveEnum;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetFile;
use App\Models\AppraisalRequest;
use App\Models\AppraisalUserConsent;
use App\Models\GuidelineSet;
use App\Models\User;
use App\Notifications\AppraisalRequestCreated;
use App\Services\Admin\AdminNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Creates appraisal requests, assets, files, and related notifications.
 */
class AppraisalRequestService
{
    public function createFromRequest(Request $request): AppraisalRequest
    {
        $validated = method_exists($request, 'validated')
            ? $request->validated()
            : $request->all();

        $guardName = config('auth.defaults.guard', 'web');
        $requestUser = $request->user();
        $customerRoleName = 'Customer';
        $customerRoleExists = Role::query()
            ->where('name', $customerRoleName)
            ->where('guard_name', $guardName)
            ->exists();

        $submitter = $requestUser;
        if ($requestUser && $customerRoleExists) {
            $submitter = User::query()
                ->whereKey($requestUser->getKey())
                ->role($customerRoleName, $guardName)
                ->first() ?? $requestUser;
        }

        $format = 'digital';
        $copies = 0;
        $reportType = 'terinci';

        // Kolom `purpose` masih wajib di schema, jadi set default aman.
        $purpose = $validated['purpose'] ?? 'jual_beli';
        $clientNameInput = trim((string) ($validated['client_name'] ?? ''));
        $clientName = $clientNameInput !== '' ? $clientNameInput : ($submitter?->name ?? null);
        $guidelineSetId = $this->resolveGuidelineSetId();
        $consentSnapshot = $this->resolveConsentSnapshot($request, $submitter);

        if (! $guidelineSetId) {
            throw ValidationException::withMessages([
                'guideline_set_id' => 'Guideline acuan belum tersedia. Aktifkan guideline terlebih dahulu.',
            ]);
        }

        return DB::transaction(function () use ($request, $validated, $format, $copies, $reportType, $purpose, $clientName, $submitter, $guardName, $guidelineSetId, $consentSnapshot) {

            $appraisalRequest = AppraisalRequest::create([
                'user_id' => $submitter?->getKey() ?? Auth::id(),
                'guideline_set_id' => $guidelineSetId,
                'purpose' => $purpose,
                'valuation_objective' => ValuationObjectiveEnum::KajianNilaiPasarRange,
                'client_name' => $clientName,
                'sertifikat_on_hand_confirmed' => (bool) ($validated['sertifikat_on_hand_confirmed'] ?? false),
                'certificate_not_encumbered_confirmed' => (bool) ($validated['certificate_not_encumbered_confirmed'] ?? false),
                'certificate_statements_accepted_at' => now(),
                'certificate_statement_ip' => (string) $request->ip(),
                'certificate_statement_user_agent' => substr((string) $request->userAgent(), 0, 255),
                'report_type' => $reportType,
                'report_format' => $format,
                'physical_copies_count' => $copies,
                'requested_at' => now(),
                'consent_accepted_at' => $consentSnapshot['accepted_at'],
                'consent_version' => $consentSnapshot['version'],
                'consent_hash' => $consentSnapshot['hash'],
                'consent_ip' => $consentSnapshot['ip'],
                'consent_user_agent' => $consentSnapshot['user_agent'],
                'status' => AppraisalStatusEnum::Submitted,
            ]);

            $baseDir = "appraisal-requests/{$appraisalRequest->id}";

            $assetsPayload = $validated['assets'] ?? [];

            foreach ($assetsPayload as $i => $row) {
                // Support payload baru: assets[i][data] JSON string
                $a = $this->normalizeAssetRow($row);

                $assetType = $this->mapAssetTypeToDb($a['type'] ?? $a['asset_type'] ?? null);
                $isLandOnly = $assetType === AssetTypeEnum::TANAH->value;
                $hasBuilding = ! $isLandOnly;

                [$lat, $lng] = $this->resolveCoordinates($a);

                $address = $a['address'] ?? null;
                $mapsLink = $a['maps_link'] ?? $a['map_url'] ?? null;

                if (!$address) {
                    throw ValidationException::withMessages([
                        "assets.$i.address" => "Alamat aset wajib diisi.",
                    ]);
                }

                if (($lat === null || $lng === null) && !$mapsLink) {
                    throw ValidationException::withMessages([
                        "assets.$i.location" => "Lokasi wajib diisi (koordinat atau link Google Maps).",
                    ]);
                }

                $asset = AppraisalAsset::create([
                    'appraisal_request_id' => $appraisalRequest->id,
                    'asset_type' => $assetType,
                    'peruntukan' => $a['peruntukan'] ?? null,
                    'title_document' => $a['title_document'] ?? null,
                    'land_shape' => $a['land_shape'] ?? null,
                    'land_position' => $a['land_position'] ?? null,
                    'land_condition' => $a['land_condition'] ?? null,
                    'topography' => $a['topography'] ?? null,

                    'land_area' => $a['land_area'] ?? null,
                    'building_area' => $hasBuilding ? ($a['building_area'] ?? null) : null,
                    'building_floors' => $hasBuilding ? ($a['floors'] ?? $a['building_floors'] ?? null) : null,
                    'build_year' => $hasBuilding ? ($a['build_year'] ?? null) : null,
                    'renovation_year' => $hasBuilding ? ($a['renovation_year'] ?? null) : null,
                    'frontage_width' => $a['frontage_width'] ?? null,
                    'access_road_width' => $a['access_road_width'] ?? null,

                    'province_id' => $this->normalizeLocationCode($a['province_id'] ?? null, 2),
                    'regency_id' => $this->normalizeLocationCode($a['regency_id'] ?? null, 4),
                    'district_id' => $this->normalizeLocationCode($a['district_id'] ?? null, 7),
                    'village_id' => $this->normalizeLocationCode($a['village_id'] ?? null, 10),

                    'address' => $address,
                    'coordinates_lat' => $lat,
                    'coordinates_lng' => $lng,

                    'maps_link' => $mapsLink,
                ]);

                $assetDir = "$baseDir/assets/{$asset->id}";

                $certFiles = $this->getCertificateFiles($request, $i);

                if (count($certFiles) === 0) {
                    throw ValidationException::withMessages([
                        "assets.$i.certificate" => "Sertifikat wajib diunggah.",
                    ]);
                }

                foreach ($certFiles as $idx => $file) {
                    $path = $this->storeLocalFile($file, "$assetDir/documents/certificate", "Sertifikat-" . ($idx + 1));
                    $this->createAssetFile($asset->id, 'doc_certs', $file, $path);
                }

                // PBB: wajib
                $pbbFile = $this->getFirstFile($request, [
                    "assets.$i.documents.pbb",
                    "assets.$i.doc_pbb",
                ]);

                if (!$pbbFile) {
                    throw ValidationException::withMessages([
                        "assets.$i.pbb" => "PBB terbaru wajib diunggah.",
                    ]);
                }

                $pbbPath = $this->storeLocalFile($pbbFile, "$assetDir/documents/pbb", "PBB");
                $this->createAssetFile($asset->id, 'doc_pbb', $pbbFile, $pbbPath);

                // IMB: opsional, hanya kalau ada bangunan
                $imbFile = $this->getFirstFile($request, [
                    "assets.$i.documents.imb",
                    "assets.$i.doc_imb",
                ]);

                if ($hasBuilding && $imbFile) {
                    $imbPath = $this->storeLocalFile($imbFile, "$assetDir/documents/imb", "IMB");
                    $this->createAssetFile($asset->id, 'doc_imb', $imbFile, $imbPath);
                }

                $photoGroups = [
                    // group => [type, newPath, legacyPath, dir, prefix]
                    'akses_jalan' => ['photo_access_road', "assets.$i.photos.akses_jalan", "assets.$i.photos_access_road", "photos/access_road", "AksesJalan"],
                    'depan'       => ['photo_front',       "assets.$i.photos.depan",       "assets.$i.photos_front",       "photos/front",       "DepanAset"],
                    'dalam'       => ['photo_interior',    "assets.$i.photos.dalam",       "assets.$i.photos_interior",    "photos/interior",    "DalamAset"],
                ];

                foreach ($photoGroups as $group => [$type, $newKey, $oldKey, $dir, $prefix]) {
                    $files = $this->getFilesArray($request, [$newKey, $oldKey]);

                    foreach ($files as $idx => $file) {
                        if (!$file) continue;

                        $path = $this->storeLocalFile($file, "$assetDir/$dir", $prefix . '-' . ($idx + 1));
                        $this->createAssetFile($asset->id, $type, $file, $path);
                    }
                }
            }

            if ($submitter) {
                $submitter->notify(
                    new AppraisalRequestCreated(
                        $appraisalRequest->id,
                        $appraisalRequest->request_number ?? null
                    )
                );
            }

            $adminUsers = app(AdminNotificationService::class)->recipients(Auth::id());

            if ($adminUsers->isNotEmpty()) {
                $requestNumber = $appraisalRequest->request_number ?? ('#' . $appraisalRequest->id);
                $creatorName = $request->user()?->name ?? 'User';
                $url = route('admin.appraisal-requests.show', ['appraisalRequest' => $appraisalRequest->id]);

                app(AdminNotificationService::class)->notifyAdmins(
                    'Permohonan penilaian baru',
                    "{$requestNumber} dibuat oleh {$creatorName}.",
                    $url,
                    'heroicon-o-clipboard-document-check',
                    Auth::id(),
                );
            }

            return $appraisalRequest;
        });
    }

    private function resolveGuidelineSetId(): ?int
    {
        $activeId = GuidelineSet::query()
            ->where('is_active', true)
            ->value('id');

        if ($activeId) {
            return (int) $activeId;
        }

        $latestId = GuidelineSet::query()
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('id');

        return $latestId ? (int) $latestId : null;
    }

    private function resolveConsentSnapshot(Request $request, ?User $submitter): array
    {
        $sessionVersion = $request->session()->get('appraisal_consent.version');
        $sessionHash = $request->session()->get('appraisal_consent.hash');
        $sessionDocumentId = $request->session()->get('appraisal_consent.document_id');

        $consent = null;

        if ($submitter?->getKey()) {
            $consentQuery = AppraisalUserConsent::query()
                ->where('user_id', $submitter->getKey())
                ->latest('accepted_at');

            if ($sessionDocumentId) {
                $consent = (clone $consentQuery)
                    ->where('consent_document_id', $sessionDocumentId)
                    ->first();
            }

            if (! $consent && $sessionVersion && $sessionHash) {
                $consent = (clone $consentQuery)
                    ->where('version', $sessionVersion)
                    ->where('hash', $sessionHash)
                    ->first();
            }
        }

        return [
            'accepted_at' => $consent?->accepted_at ?? now(),
            'version' => $sessionVersion ?: ($consent?->version ?: null),
            'hash' => $sessionHash ?: ($consent?->hash ?: null),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ];
    }

    private function normalizeAssetRow(array $row): array
    {
        if (isset($row['data']) && is_string($row['data'])) {
            $decoded = json_decode($row['data'], true);
            if (is_array($decoded)) return $decoded;
        }
        return $row;
    }

    private function resolveCoordinates(array $a): array
    {
        if (isset($a['coordinates_lat']) || isset($a['coordinates_lng'])) {
            return [$this->asFloatOrNull($a['coordinates_lat'] ?? null), $this->asFloatOrNull($a['coordinates_lng'] ?? null)];
        }

        return $this->parseCoordinates($a['coordinates'] ?? null);
    }

    private function getCertificateFiles(Request $request, int $i): array
    {
        $single = $request->file("assets.$i.documents.certificate");
        if ($single) return [$single];

        $arr = (array) $request->file("assets.$i.doc_certs", []);
        return array_values(array_filter($arr));
    }

    private function getFirstFile(Request $request, array $paths)
    {
        foreach ($paths as $p) {
            $f = $request->file($p);
            if ($f) return $f;
        }
        return null;
    }

    private function getFilesArray(Request $request, array $paths): array
    {
        foreach ($paths as $p) {
            $files = $request->file($p, null);
            if (is_array($files) && count($files)) return array_values(array_filter($files));
        }
        return [];
    }

    private function createAssetFile(int $assetId, string $type, $file, string $path): void
    {
        AppraisalAssetFile::create([
            'appraisal_asset_id' => $assetId,
            'type' => $type,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);
    }

    private function storeLocalFile($file, string $dir, string $prefix): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'bin';
        $name = $prefix . '-' . Str::uuid() . '.' . $ext;

        return $file->storeAs($dir, $name, 'public');
    }

    private function mapAssetTypeToDb(?string $type): ?string
    {
        if (!$type) return null;

        // Normalize legacy values
        if ($type === 'land') {
            return AssetTypeEnum::TANAH->value;
        }

        // DB only accepts: tanah | tanah_bangunan
        if ($type === AssetTypeEnum::TANAH->value) {
            return AssetTypeEnum::TANAH->value;
        }

        return AssetTypeEnum::TANAH_BANGUNAN->value;
    }

    private function parseCoordinates(mixed $raw): array
    {
        if ($raw === null || $raw === '') return [null, null];

        if (is_string($raw)) {
            $trim = trim($raw);

            // JSON
            if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                $decoded = json_decode($trim, true);
                if (is_array($decoded)) {
                    $lat = $decoded['lat'] ?? $decoded['latitude'] ?? null;
                    $lng = $decoded['lng'] ?? $decoded['longitude'] ?? null;
                    return [$this->asFloatOrNull($lat), $this->asFloatOrNull($lng)];
                }
            }

            // CSV "-6.2,106.8"
            if (str_contains($trim, ',')) {
                [$a, $b] = array_pad(array_map('trim', explode(',', $trim, 2)), 2, null);
                return [$this->asFloatOrNull($a), $this->asFloatOrNull($b)];
            }
        }

        // array input
        if (is_array($raw)) {
            $lat = $raw['lat'] ?? $raw['latitude'] ?? null;
            $lng = $raw['lng'] ?? $raw['longitude'] ?? null;
            return [$this->asFloatOrNull($lat), $this->asFloatOrNull($lng)];
        }

        return [null, null];
    }

    private function asFloatOrNull($v): ?float
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (float) $v;
        return null;
    }

    private function normalizeLocationCode(mixed $value, int $length): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) > $length) {
            return null;
        }

        return str_pad($digits, $length, '0', STR_PAD_LEFT);
    }
}
