@php
    // Pastikan $items selalu array
    $items = is_array($items ?? null) ? $items : [];
@endphp

<div class="space-y-3" x-data="{ selected: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }} }">

    @if (empty($items))
        <div class="text-sm text-gray-500">
            Tidak ada data pembanding (cek koordinat, district_id, peruntukan, atau hasil API).
        </div>
    @else
        <div class="grid grid-cols-1 gap-3">
            @foreach ($items as $item)
                @php
                    $id = (string) data_get($item, 'id');
                    $img = data_get($item, 'image_url');
                    if ($img && !str_starts_with($img, 'http')) {
                        $img = url($img);
                    }

                     $alamat = trim((string) (data_get($item, 'alamat_data') ?? ''));

                    $kota = trim((string) (data_get($item, 'regency.name') ?? ''));
                    $kelurahan = trim((string) (data_get($item, 'village.name') ?? ''));

                    // Normalisasi (opsional): ubah "KOTA PALEMBANG" -> "Kota Palembang"
                    $toTitle = fn ($s) => $s !== '' ? ucwords(strtolower($s)) : '';
                    $kota = $toTitle($kota);
                    $kelurahan = $toTitle($kelurahan);

                    // Susun lokasi: cukup kelurahan + kota
                    $lokasiParts = [];
                    if ($kelurahan !== '') $lokasiParts[] = "Kel. {$kelurahan}";
                    if ($kota !== '') $lokasiParts[] = $kota; // sudah mengandung "Kota ..." jika data memang begitu

                    $lokasi = implode(', ', $lokasiParts);

                    // Gabungkan
                    $judul = $alamat !== '' ? $alamat : ('Pembanding #' . (string) data_get($item, 'id'));
                    if ($lokasi !== '') $judul .= ", {$lokasi}";

                    $harga = (int) (data_get($item, 'harga') ?? 0);
                    $lt = data_get($item, 'luas_tanah');
                    $lb = data_get($item, 'luas_bangunan');

                    $score = data_get($item, 'score');
                    $rank = data_get($item, 'priority_rank');
                    $distM = data_get($item, 'distance');
                    $distKm = is_numeric($distM) ? round(((float) $distM) / 1000, 2) : null;

                    $unit = is_numeric($lt) && (float) $lt > 0 && $harga > 0 ? round($harga / (float) $lt) : null;
                @endphp

                <label class="flex gap-3 p-3 rounded-xl border border-gray-200 hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" class="mt-1" value="{{ $id }}" x-model="selected" />

                    <div class="w-20 h-20 flex-shrink-0 rounded-lg overflow-hidden bg-gray-100">
                        @if ($img)
                            <img src="{{ $img }}" class="w-full h-full object-cover"
                                alt="Pembanding {{ $id }}">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-xs text-gray-400">
                                No image
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium text-gray-900 truncate">
                            {{ $judul }}
                        </div>

                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-600">
                            <span>Harga: Rp{{ number_format($harga, 0, ',', '.') }}</span>
                            @if ($unit)
                                <span>Rp/m²: Rp{{ number_format($unit, 0, ',', '.') }}</span>
                            @endif
                            @if ($lt !== null && $lt !== '')
                                <span>LT: {{ $lt }} m²</span>
                            @endif
                            @if ($lb !== null && $lb !== '')
                                <span>LB: {{ $lb }} m²</span>
                            @endif
                            @if ($score !== null && $score !== '')
                                <span>Score: {{ $score }}</span>
                            @endif
                            @if ($rank !== null && $rank !== '')
                                <span>Rank: {{ $rank }}</span>
                            @endif
                            @if ($distKm !== null)
                                <span>Jarak: {{ $distKm }} km</span>
                            @endif
                        </div>

                        <div class="mt-1 text-xs text-gray-500">
                            @php
                                $p = data_get($item, 'peruntukan');

                                $peruntukanText =
                                    data_get($item, 'peruntukan.slug') ??
                                    (is_array($p)
                                        ? $p['slug'] ?? ($p['nama'] ?? ($p['name'] ?? ''))
                                        : (is_string($p)
                                            ? $p
                                            : ''));
                            @endphp

                            {{ $peruntukanText }}
                            @if (data_get($item, 'tanggal_data'))
                                •
                                {{ \Illuminate\Support\Carbon::parse(data_get($item, 'tanggal_data'))->format('d M Y') }}
                            @endif
                        </div>
                    </div>
                </label>
            @endforeach
        </div>
    @endif
</div>
