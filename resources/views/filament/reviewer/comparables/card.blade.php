@php
    $asset = $record?->asset;
    $request = $asset?->request;
    $img = $record?->image_url ?: 'https://ui-avatars.com/api/?name=PB';
    $isSelected = (bool) ($record?->is_selected ?? false);
    $rank = $record?->manual_rank ?? $record?->rank ?? '-';
    $indicationValue = $record?->indication_value
        ? 'Rp ' . number_format($record->indication_value, 0, ',', '.')
        : '-';
@endphp

<div @class([
    'rounded-xl border p-4 flex flex-col gap-4 shadow-sm transition-all duration-200',
    'border-emerald-500/40 bg-emerald-950/30' => $isSelected,
    'border-slate-700/50 bg-slate-900/60 hover:border-slate-600/70' => !$isSelected,
])>

    {{-- Header: Image + Meta --}}
    <div class="flex items-start gap-3">

        {{-- Thumbnail --}}
        <div class="relative h-20 w-28 shrink-0 overflow-hidden rounded-lg bg-slate-800 shadow">
            <img src="{{ $img }}" alt="comparable" class="h-full w-full object-cover">
            {{-- Rank Badge --}}
            <div class="absolute top-1 left-1 bg-black/60 text-white text-[10px] px-1.5 py-0.5 rounded font-mono">
                #{{ $rank }}
            </div>
        </div>

        {{-- Title + Badge row --}}
        <div class="flex-1 min-w-0 space-y-1.5">
            {{-- Request number + toggle button --}}
            <div class="flex items-center justify-between gap-2">
                <span class="text-[11px] font-medium text-slate-400 tracking-wide uppercase">
                    {{ $request?->request_number ?? '-' }}
                </span>
                <button
                    wire:click="callTableAction('toggleSelect', '{{ $record?->getKey() }}')"
                    type="button"
                    @class([
                        'shrink-0 px-2.5 py-1 text-[11px] font-semibold rounded-full border transition-colors',
                        'bg-emerald-600 border-emerald-500 text-white' => $isSelected,
                        'bg-slate-800 border-slate-600 text-slate-300 hover:bg-slate-700' => !$isSelected,
                    ])>
                    {{ $isSelected ? '✓ Dipakai' : 'Pilih' }}
                </button>
            </div>

            {{-- Address --}}
            <div class="text-sm font-semibold text-slate-100 leading-snug line-clamp-2">
                {{ $asset?->address ?? '-' }}
            </div>

            {{-- Badges row --}}
            <div class="flex flex-wrap items-center gap-1.5">
                <span class="px-2 py-0.5 rounded-md bg-amber-500/15 border border-amber-500/30 text-amber-300 text-[11px] font-mono">
                    ID {{ $record->external_id }}
                </span>
                <span @class([
                    'px-2 py-0.5 rounded-md text-[11px] font-semibold',
                    'bg-emerald-500/15 border border-emerald-500/30 text-emerald-300' => ($record->score ?? 0) >= 0.7,
                    'bg-yellow-500/15 border border-yellow-500/30 text-yellow-300' => ($record->score ?? 0) >= 0.4 && ($record->score ?? 0) < 0.7,
                    'bg-slate-700/50 border border-slate-600/40 text-slate-400' => ($record->score ?? 0) < 0.4,
                ])>
                    Score {{ number_format($record->score ?? 0, 3) }}
                </span>
                @if($record->distance_meters)
                    <span class="px-2 py-0.5 rounded-md bg-blue-500/15 border border-blue-500/30 text-blue-300 text-[11px]">
                        {{ number_format($record->distance_meters, 0, ',', '.') }} m
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Divider --}}
    <div class="border-t border-slate-700/50"></div>

    {{-- Stats Grid --}}
    <div class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-xs">
        @foreach ([
            ['label' => 'Luas Tanah', 'value' => $record?->raw_land_area ? number_format($record->raw_land_area, 0, ',', '.') . ' m²' : '-'],
            ['label' => 'Luas Bangunan', 'value' => $record?->raw_building_area ? number_format($record->raw_building_area, 0, ',', '.') . ' m²' : '-'],
            ['label' => 'Peruntukan', 'value' => $record?->raw_peruntukan ?? '-'],
            ['label' => 'Tgl Data', 'value' => $record?->raw_data_date ?? '-'],
            ['label' => 'Harga/m²', 'value' => $record?->raw_unit_price_land ? 'Rp ' . number_format($record->raw_unit_price_land, 0, ',', '.') : '-'],
            ['label' => 'Total Adj', 'value' => $record?->total_adjustment_percent !== null ? number_format($record->total_adjustment_percent, 2) . '%' : '-'],
        ] as $stat)
            <div class="flex flex-col gap-0.5">
                <span class="text-[10px] uppercase tracking-wide text-slate-500 font-medium">{{ $stat['label'] }}</span>
                <span class="text-slate-200 font-medium">{{ $stat['value'] }}</span>
            </div>
        @endforeach
    </div>

    {{-- Indication Value Footer --}}
    <div @class([
        'rounded-lg px-3 py-2 flex items-center justify-between',
        'bg-emerald-900/40 border border-emerald-700/40' => $record?->indication_value,
        'bg-slate-800/50 border border-slate-700/40' => !$record?->indication_value,
    ])>
        <span class="text-[11px] uppercase tracking-wide font-medium text-slate-400">Nilai Indikasi</span>
        <span @class([
            'text-sm font-bold',
            'text-emerald-300' => $record?->indication_value,
            'text-slate-500' => !$record?->indication_value,
        ])>
            {{ $indicationValue }}
        </span>
    </div>

</div>
