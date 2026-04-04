<?php

namespace App\Http\Requests\Customer;

use App\Enums\AssetTypeEnum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class StoreAppraisalRequest extends CustomerFormRequest
{

    protected function prepareForValidation(): void
    {
        $assets = (array) $this->input('assets', []);

        foreach ($assets as $i => $asset) {
            // Support payload: assets[i][data] JSON string from frontend
            if (isset($asset['data']) && is_string($asset['data'])) {
                $decoded = json_decode($asset['data'], true);
                if (is_array($decoded)) {
                    $assets[$i] = array_merge($asset, $decoded);
                }
            }

            // Map coordinates_lat/coordinates_lng into coordinates[lat/lng] for validation
            $lat = data_get($assets[$i], 'coordinates_lat');
            $lng = data_get($assets[$i], 'coordinates_lng');
            if (!isset($assets[$i]['coordinates']) && ($lat !== null || $lng !== null)) {
                $assets[$i]['coordinates'] = [
                    'lat' => $lat,
                    'lng' => $lng,
                ];
            }
        }

        $this->merge([
            'assets' => $assets,
        ]);
    }

    public function rules(): array
    {
        return [
            'sertifikat_on_hand_confirmed' => ['accepted'],
            'certificate_not_encumbered_confirmed' => ['accepted'],
            'assets' => ['required', 'array', 'min:1'],
            'assets.*.type' => ['required', 'string'],

            'assets.*.land_area' => ['required', 'numeric', 'min:0'],
            'assets.*.building_area' => ['nullable', 'numeric', 'min:0'],
            'assets.*.floors' => ['nullable', 'integer', 'min:0', 'max:200'],
            'assets.*.build_year' => ['nullable', 'integer', 'min:1900', 'max:' . now()->year],
            'assets.*.renovation_year' => ['nullable', 'integer', 'min:1900', 'max:' . now()->year],
            'assets.*.peruntukan' => ['nullable', 'string', 'max:100'],
            'assets.*.title_document' => ['required', 'string', 'max:100'],
            'assets.*.land_shape' => ['nullable', 'string', 'max:100'],
            'assets.*.land_position' => ['nullable', 'string', 'max:100'],
            'assets.*.land_condition' => ['nullable', 'string', 'max:100'],
            'assets.*.topography' => ['nullable', 'string', 'max:100'],
            'assets.*.frontage_width' => ['nullable', 'numeric', 'min:0'],
            'assets.*.access_road_width' => ['nullable', 'numeric', 'min:0'],

            'assets.*.province_id' => ['nullable', 'string'],
            'assets.*.regency_id' => ['nullable', 'string'],
            'assets.*.district_id' => ['nullable', 'string'],
            'assets.*.village_id' => ['nullable', 'string'],

            'assets.*.address' => ['nullable', 'string'],

            'assets.*.coordinates' => ['nullable', 'array'],
            'assets.*.coordinates.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'assets.*.coordinates.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'assets.*.maps_link' => ['nullable', 'string', 'max:1000'],

            'assets.*.doc_pbb' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'assets.*.doc_imb' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'assets.*.doc_old_report' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],

            'assets.*.doc_certs' => ['required', 'array', 'min:1'],
            'assets.*.doc_certs.*' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],

            'assets.*.photos_access_road' => ['required', 'array', 'min:1', 'max:5'],
            'assets.*.photos_access_road.*' => ['file', 'max:15360', 'mimes:jpg,jpeg,png,webp'],

            'assets.*.photos_front' => ['required', 'array', 'min:1', 'max:5'],
            'assets.*.photos_front.*' => ['file', 'max:15360', 'mimes:jpg,jpeg,png,webp'],

            'assets.*.photos_interior' => ['required', 'array', 'min:1', 'max:20'],
            'assets.*.photos_interior.*' => ['file', 'max:15360', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $assets = (array) $this->input('assets', []);

            foreach ($assets as $i => $asset) {
                $typeRaw = data_get($asset, 'type');
                $type = $this->normalizeAssetType($typeRaw);
                $isLand = $type === AssetTypeEnum::TANAH->value;

                $lat = data_get($asset, 'coordinates.lat');
                $lng = data_get($asset, 'coordinates.lng');
                $maps = data_get($asset, 'maps_link');

                $hasCoords = is_numeric($lat) && is_numeric($lng);
                $hasMaps = is_string($maps) && trim($maps) !== '';

                if (!$hasCoords && !$hasMaps) {
                    $v->errors()->add("assets.$i.coordinates", 'Lokasi presisi wajib diisi: pilih salah satu (pin/koordinat atau link Google Maps).');
                }

                if (!$isLand) {
                    $buildingArea = data_get($asset, 'building_area');
                    $floors = data_get($asset, 'floors');
                    $imbFile = $this->file("assets.$i.doc_imb");

                    if (!is_numeric($buildingArea)) {
                        $v->errors()->add("assets.$i.building_area", 'Luas bangunan wajib diisi untuk aset non-tanah.');
                    }

                    if (!is_numeric($floors)) {
                        $v->errors()->add("assets.$i.floors", 'Jumlah lantai wajib diisi untuk aset non-tanah.');
                    }

                    if (!is_numeric(data_get($asset, 'build_year'))) {
                        $v->errors()->add("assets.$i.build_year", 'Tahun bangun wajib diisi untuk aset non-tanah.');
                    }

                    if (!$imbFile) {
                        $v->errors()->add("assets.$i.doc_imb", 'IMB / PBG wajib diunggah untuk aset non-tanah.');
                    }
                }
            }

            $maxFileUploads = (int) ini_get('max_file_uploads');
            $uploadedCount = $this->countUploadedFiles($this->allFiles());
            $hasRequiredFileError = collect($v->errors()->keys())
                ->contains(fn ($key) => str_contains((string) $key, 'photos_') || str_contains((string) $key, 'doc_'));

            if ($maxFileUploads > 0 && $hasRequiredFileError && $uploadedCount >= $maxFileUploads) {
                $v->errors()->add(
                    'assets',
                    "Jumlah file yang diterima server mencapai batas ({$maxFileUploads} file per submit). Kurangi jumlah file per aset, atau minta admin menaikkan konfigurasi max_file_uploads di PHP."
                );
            }
        });
    }

    private function countUploadedFiles(array $files): int
    {
        $count = 0;

        $walk = function ($value) use (&$walk, &$count): void {
            if ($value instanceof UploadedFile) {
                $count++;
                return;
            }

            if ($value instanceof Collection) {
                $value = $value->all();
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $walk($item);
                }
            }
        };

        $walk($files);

        return $count;
    }

    private function normalizeAssetType(mixed $raw): ?string
    {
        if ($raw instanceof AssetTypeEnum) {
            return $raw->value;
        }

        if (!is_string($raw)) {
            return null;
        }

        $raw = trim($raw);

        $enum = AssetTypeEnum::tryFrom($raw);
        if ($enum) {
            return $enum->value;
        }

        return match ($raw) {
            'land' => AssetTypeEnum::TANAH->value,
            'house' => AssetTypeEnum::RUMAH_TINGGAL->value,
            'shophouse' => AssetTypeEnum::RUKO->value,
            'warehouse' => AssetTypeEnum::GUDANG->value,
            default => $raw,
        };
    }

    public function messages(): array
    {
        return [
            'assets.required' => 'Mohon tambahkan minimal satu aset.',
            'assets.min' => 'Mohon tambahkan minimal satu aset.',
            'sertifikat_on_hand_confirmed.accepted' => 'Anda harus menyatakan bahwa sertifikat fisik tersedia / on hand.',
            'certificate_not_encumbered_confirmed.accepted' => 'Permohonan tidak dapat dilanjutkan jika sertifikat sedang dijaminkan. Centang pernyataan bahwa sertifikat tidak sedang dijaminkan.',

            'assets.*.land_area.required' => 'Luas tanah wajib diisi.',
            'assets.*.land_area.numeric' => 'Luas tanah harus berupa angka.',
            'assets.*.land_area.min' => 'Luas tanah tidak boleh kurang dari 0.',

            'assets.*.building_area.numeric' => 'Luas bangunan harus berupa angka.',
            'assets.*.building_area.min' => 'Luas bangunan tidak boleh kurang dari 0.',
            'assets.*.floors.integer' => 'Jumlah lantai harus berupa angka bulat.',
            'assets.*.floors.min' => 'Jumlah lantai tidak boleh kurang dari 0.',
            'assets.*.floors.max' => 'Jumlah lantai terlalu besar.',
            'assets.*.build_year.integer' => 'Tahun bangun harus berupa angka bulat.',
            'assets.*.build_year.min' => 'Tahun bangun tidak valid.',
            'assets.*.build_year.max' => 'Tahun bangun tidak boleh melebihi tahun berjalan.',
            'assets.*.title_document.required' => 'Jenis dokumen tanah wajib dipilih.',
            'assets.*.frontage_width.numeric' => 'Lebar muka tanah harus berupa angka.',
            'assets.*.frontage_width.min' => 'Lebar muka tanah tidak boleh kurang dari 0.',
            'assets.*.access_road_width.numeric' => 'Lebar akses jalan harus berupa angka.',
            'assets.*.access_road_width.min' => 'Lebar akses jalan tidak boleh kurang dari 0.',

            'assets.*.doc_pbb.required' => 'FC PBB tahun terakhir wajib diunggah.',
            'assets.*.doc_pbb.file' => 'FC PBB harus berupa file yang valid.',
            'assets.*.doc_pbb.max' => 'Ukuran FC PBB maksimal 10 MB.',
            'assets.*.doc_pbb.mimes' => 'FC PBB harus berformat PDF/JPG/JPEG/PNG.',

            'assets.*.doc_imb.file' => 'FC IMB/PBG harus berupa file yang valid.',
            'assets.*.doc_imb.max' => 'Ukuran FC IMB/PBG maksimal 10 MB.',
            'assets.*.doc_imb.mimes' => 'FC IMB/PBG harus berformat PDF/JPG/JPEG/PNG.',

            'assets.*.doc_certs.required' => 'Sertifikat tanah wajib diunggah.',
            'assets.*.doc_certs.array' => 'Format sertifikat tanah tidak valid.',
            'assets.*.doc_certs.min' => 'Minimal 1 file sertifikat tanah harus diunggah.',
            'assets.*.doc_certs.*.required' => 'File sertifikat tanah tidak boleh kosong.',
            'assets.*.doc_certs.*.file' => 'Setiap sertifikat tanah harus berupa file yang valid.',
            'assets.*.doc_certs.*.max' => 'Ukuran setiap file sertifikat tanah maksimal 10 MB.',
            'assets.*.doc_certs.*.mimes' => 'Sertifikat tanah harus berformat PDF/JPG/JPEG/PNG.',

            'assets.*.photos_access_road.required' => 'Foto akses jalan wajib diunggah untuk setiap aset.',
            'assets.*.photos_access_road.array' => 'Format foto akses jalan tidak valid.',
            'assets.*.photos_access_road.min' => 'Minimal 1 foto akses jalan harus diunggah.',
            'assets.*.photos_access_road.max' => 'Maksimal 5 foto akses jalan per aset.',
            'assets.*.photos_access_road.*.file' => 'Setiap foto akses jalan harus berupa file yang valid.',
            'assets.*.photos_access_road.*.max' => 'Ukuran setiap foto akses jalan maksimal 15 MB.',
            'assets.*.photos_access_road.*.mimes' => 'Foto akses jalan harus berformat JPG/JPEG/PNG/WEBP.',

            'assets.*.photos_front.required' => 'Foto tampak depan aset wajib diunggah untuk setiap aset.',
            'assets.*.photos_front.array' => 'Format foto tampak depan aset tidak valid.',
            'assets.*.photos_front.min' => 'Minimal 1 foto tampak depan aset harus diunggah.',
            'assets.*.photos_front.max' => 'Maksimal 5 foto tampak depan aset per aset.',
            'assets.*.photos_front.*.file' => 'Setiap foto tampak depan aset harus berupa file yang valid.',
            'assets.*.photos_front.*.max' => 'Ukuran setiap foto tampak depan aset maksimal 15 MB.',
            'assets.*.photos_front.*.mimes' => 'Foto tampak depan aset harus berformat JPG/JPEG/PNG/WEBP.',

            'assets.*.photos_interior.required' => 'Foto tampak dalam aset wajib diunggah untuk setiap aset.',
            'assets.*.photos_interior.array' => 'Format foto tampak dalam aset tidak valid.',
            'assets.*.photos_interior.min' => 'Minimal 1 foto tampak dalam aset harus diunggah.',
            'assets.*.photos_interior.max' => 'Maksimal 20 foto tampak dalam per aset.',
            'assets.*.photos_interior.*.file' => 'Setiap foto tampak dalam aset harus berupa file yang valid.',
            'assets.*.photos_interior.*.max' => 'Ukuran setiap foto tampak dalam aset maksimal 15 MB.',
            'assets.*.photos_interior.*.mimes' => 'Foto tampak dalam aset harus berformat JPG/JPEG/PNG/WEBP.',
        ];
    }
}
