@php /* items passed in viewData */ @endphp
<div class="space-y-3">
    @forelse(($items ?? []) as $item)
        <div class="flex gap-3 rounded-lg border border-slate-800 bg-slate-900/70 p-3">
            <div class="h-16 w-24 overflow-hidden rounded-md bg-slate-800">
                <img src="{{ $item['image_url'] ?? 'https://ui-avatars.com/api/?name=PB' }}" class="h-full w-full object-cover" alt="img">
            </div>
            <div class="flex-1 text-xs text-slate-200 space-y-1">
                <div class="font-semibold text-sm text-slate-100">ID {{ $item['id'] ?? '-' }}</div>
                <div class="flex flex-wrap gap-3">
                    <span>Score: <span class="text-emerald-300">{{ number_format($item['score'] ?? 0, 3) }}</span></span>
                    <span>Jarak: {{ number_format($item['distance'] ?? 0, 1, ',', '.') }} m</span>
                    <span>Luas Tanah: {{ number_format($item['luas_tanah'] ?? 0, 0, ',', '.') }} m²</span>
                    <span>Luas Bangunan: {{ number_format($item['luas_bangunan'] ?? 0, 0, ',', '.') }} m²</span>
                </div>
                <div>Harga: Rp {{ number_format($item['harga'] ?? 0, 0, ',', '.') }}</div>
            </div>
        </div>
    @empty
        <div class="text-slate-400 text-sm">Belum ada hasil. Isi koordinat/peruntukan aset lalu set limit & range.</div>
    @endforelse
</div>
