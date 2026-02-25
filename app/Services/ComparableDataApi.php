<?php

namespace App\Services;

use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetComparable;
use App\Models\ExternalApiToken;
use App\Models\LandAdjustment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ComparableDataApi
{
    private string $baseUrl;
    private string $provider;
    private ?string $email;
    private ?string $password;
    private string $deviceName;

    public function __construct()
    {
        $this->baseUrl = rtrim(Config::get('comparable.base_url'), '/');
        $this->provider = Config::get('comparable.provider_key', 'pd_kjpp_hjar');
        $this->email = Config::get('comparable.credentials.email');
        $this->password = Config::get('comparable.credentials.password');
        $this->deviceName = Config::get('comparable.credentials.device_name', 'digipro-service');
    }

    public function fetchSimilarForAsset(AppraisalAsset $asset, ?int $limit = null, ?float $rangeKm = null): array
    {
        $limit ??= (int) Config::get('comparable.default_limit', 100);
        $rangeKm ??= (float) Config::get('comparable.default_range_km', 10);

        if (! $asset->coordinates_lat || ! $asset->coordinates_lng || ! $asset->district_id || ! $asset->peruntukan) {
            throw new \InvalidArgumentException('Asset belum memiliki koordinat, district_id, atau peruntukan.');
        }

        $payload = [
            'latitude' => (float) $asset->coordinates_lat,
            'longitude' => (float) $asset->coordinates_lng,
            'district_id' => (string) $asset->district_id,
            'peruntukan' => (string) $asset->peruntukan,
            'limit' => $limit,
            'range_km' => $rangeKm,
        ];

        if ($asset->land_area) {
            $payload['luas_tanah'] = (float) $asset->land_area;
        }

        if ($asset->building_area) {
            $payload['luas_bangunan'] = (float) $asset->building_area;
        }

        $response = $this->request()
            ->post('/v1/pembandings/similar', $payload);

        if ($response->status() === 401) {
            $this->forceRefresh();
            $response = $this->request()->post('/v1/pembandings/similar', $payload);
        }

        $response->throw();

        return Arr::get($response->json(), 'data', []);
    }

    public function upsertComparables(AppraisalAsset $asset, array $items): int
    {
        $created = 0;

        foreach ($items as $item) {
            $externalId = Arr::get($item, 'id');
            if ($externalId === null) {
                continue;
            }

            $rawPrice = (int) (Arr::get($item, 'harga') ?? 0);
            $rawLandArea = $this->toFloat(Arr::get($item, 'luas_tanah'));
            $rawBuildingArea = $this->toFloat(Arr::get($item, 'luas_bangunan'));

            $rawUnitPrice = null;
            if ($rawLandArea && $rawPrice) {
                $rawUnitPrice = $rawPrice / $rawLandArea;
            }

            $autoAdjust = $this->computeAutoAdjustPercent($asset, $rawLandArea, $item);

            $rawDate = Arr::get($item, 'tanggal_data');
            $dateOnly = $this->normalizeDate($rawDate);

            $data = [
                'external_source' => $this->provider,
                'image_url' => Arr::get($item, 'image_url'),
                'raw_price' => $rawPrice > 0 ? $rawPrice : null,
                'raw_land_area' => $rawLandArea,
                'raw_building_area' => $rawBuildingArea,
                'raw_unit_price_land' => $rawUnitPrice ? round($rawUnitPrice, 2) : null,
                'raw_peruntukan' => Arr::get($item, 'peruntukan.slug') ?? Arr::get($item, 'peruntukan'),
                'raw_data_date' => $dateOnly,
                'score' => $this->toFloat(Arr::get($item, 'score')),
                'weight' => $this->toFloat(Arr::get($item, 'priority_rank')),
                'distance_meters' => $this->toFloat(Arr::get($item, 'distance')),
                'rank' => $this->toInt(Arr::get($item, 'priority_rank')),
                'manual_rank' => $this->toInt(Arr::get($item, 'priority_rank')),
                'auto_adjust_percent' => $autoAdjust,
                'total_adjustment_percent_low' => $autoAdjust,
                'total_adjustment_percent_high' => $autoAdjust,
                'indication_value' => (int) ($rawPrice ?? 0),
                'snapshot_json' => $item,
            ];

            $record = AppraisalAssetComparable::query()->firstOrNew([
                'appraisal_asset_id' => $asset->id,
                'external_id' => $externalId,
                'external_source' => $this->provider,
            ]);

            // Pertahankan pilihan reviewer jika sudah pernah di-set
            if ($record->exists) {
                $data['is_selected'] = $record->is_selected;
                $data['manual_rank'] = $record->manual_rank ?? $data['manual_rank'];
            } else {
                $data['is_selected'] = false;
            }

            $record->fill($data)->save();

            $created++;
        }

        return $created;
    }

    public function syncAssetRange(AppraisalAsset $asset): ?array
    {
        $values = $asset->comparables()
            ->whereNotNull('indication_value')
            ->pluck('indication_value')
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->values();

        if ($values->isEmpty()) {
            return null;
        }

        $low = (int) $values->min();
        $high = (int) $values->max();
        $mid = (int) round($values->avg());

        $asset->update([
            'estimated_value_low' => $low,
            'estimated_value_high' => $high,
            'market_value_final' => $mid,
        ]);

        return compact('low', 'high', 'mid');
    }

    private function request(): PendingRequest
    {
        $token = $this->getValidAccessToken();

        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->withToken($token);
    }

    private function getValidAccessToken(): string
    {
        $tokenRecord = $this->tokenRecord();

        if ($tokenRecord->isAccessTokenValid()) {
            return $tokenRecord->access_token;
        }

        if ($tokenRecord->isRefreshTokenValid()) {
            try {
                return $this->refreshToken($tokenRecord);
            } catch (Throwable $e) {
                Log::warning('Refresh comparable API token gagal, fallback login.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->login($tokenRecord);
    }

    private function forceRefresh(): void
    {
        $tokenRecord = $this->tokenRecord();

        if ($tokenRecord->isRefreshTokenValid()) {
            $this->refreshToken($tokenRecord);
            return;
        }

        $this->login($tokenRecord);
    }

    private function login(ExternalApiToken $tokenRecord): string
    {
        if (! $this->email || ! $this->password) {
            throw new \RuntimeException('Credensial API pembanding belum di-set di .env');
        }

        $response = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->post('/auth/login', [
                'email' => $this->email,
                'password' => $this->password,
                'device_name' => $this->deviceName,
            ]);

        $response->throw();

        $data = $response->json('data') ?? [];

        $tokenRecord->fill([
            'access_token' => Arr::get($data, 'access_token'),
            'refresh_token' => Arr::get($data, 'refresh_token'),
            'access_token_expires_at' => now()->addSeconds((int) (Arr::get($data, 'expires_in', 3600))),
            'refresh_token_expires_at' => now()->addDays(30),
            'last_refreshed_at' => now(),
            'last_error' => null,
        ])->save();

        return $tokenRecord->access_token;
    }

    private function refreshToken(ExternalApiToken $tokenRecord): string
    {
        $refreshToken = $tokenRecord->refresh_token;

        if (! $refreshToken) {
            throw new \RuntimeException('Refresh token tidak tersedia.');
        }

        $response = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->post('/auth/refresh', [
                'refresh_token' => $refreshToken,
                'device_name' => $this->deviceName . '-refresh',
            ]);

        $response->throw();

        $data = $response->json('data') ?? [];

        $tokenRecord->fill([
            'access_token' => Arr::get($data, 'access_token'),
            'refresh_token' => Arr::get($data, 'refresh_token', $refreshToken),
            'access_token_expires_at' => now()->addSeconds((int) (Arr::get($data, 'expires_in', 3600))),
            'refresh_token_expires_at' => now()->addDays(30),
            'last_refreshed_at' => now(),
            'last_error' => null,
        ])->save();

        return $tokenRecord->access_token;
    }

    private function tokenRecord(): ExternalApiToken
    {
        return ExternalApiToken::firstOrCreate(
            ['provider' => $this->provider],
            ['last_error' => null]
        );
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = (float) $value;
        return is_finite($parsed) ? $parsed : null;
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = (int) $value;
        return $parsed >= 0 ? $parsed : null;
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (is_string($value) && strlen($value) >= 10) {
            return substr($value, 0, 10);
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (Throwable $e) {
            return null;
        }
    }

    private function computeAutoAdjustPercent(AppraisalAsset $asset, ?float $compLandArea, array $item): ?float
    {
        $landAreaSubject = $this->toFloat($asset->land_area);
        $docGrid = Config::get('comparable_adjustments.document_tiers', []);
        $landGrid = Config::get('comparable_adjustments.land_area_ratio_grid', []);

        $adjustments = [];

        // Land area ratio
        if ($landAreaSubject && $compLandArea) {
            $ratio = $compLandArea / $landAreaSubject;
            $adjustments[] = $this->lookupGridPercent($ratio, $landGrid);
        }

        // Document tier (requires subject doc slug; belum tersedia, jadi skip)
        // Placeholder: if both comp and subject slug available, compare tiers. 
        $subjectDoc = null; // TODO: isi jika field tersedia
        $compDoc = Arr::get($item, 'dokumen_tanah.slug');
        if ($subjectDoc && $compDoc && isset($docGrid[$subjectDoc], $docGrid[$compDoc])) {
            $delta = $docGrid[$subjectDoc] - $docGrid[$compDoc];
            $adjustments[] = $delta;
        }

        if (empty($adjustments)) {
            return null;
        }

        // Jumlahkan penyesuaian objektif
        return array_sum($adjustments);
    }

    private function lookupGridPercent(float $ratio, array $grid): float
    {
        // grid keys string numbers
        $candidates = [];
        foreach ($grid as $key => $value) {
            $limit = (float) $key;
            if ($ratio <= $limit) {
                $candidates[$limit] = (float) $value;
            }
        }

        if (empty($candidates)) {
            return 0.0;
        }

        ksort($candidates);
        return reset($candidates);
    }
}
