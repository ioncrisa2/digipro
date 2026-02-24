@php
    $mime = $record->mime ?? '';
    $isImage = str_starts_with($mime, 'image/');
    $isPdf = $mime === 'application/pdf';
@endphp

<div class="space-y-4">
    <div class="text-sm text-gray-600">
        {{ $record->original_name ?? basename($record->path) }}
    </div>

    @if ($isImage)
        <div class="w-full overflow-auto rounded-lg border border-gray-200">
            <img src="{{ $previewUrl }}" alt="Preview" class="max-h-[70vh] w-auto mx-auto" />
        </div>
    @elseif ($isPdf)
        <div class="w-full overflow-hidden rounded-lg border border-gray-200" style="height: 70vh;">
            <iframe src="{{ $previewUrl }}" class="h-full w-full" title="Preview"></iframe>
        </div>
    @else
        <div class="rounded-lg border border-gray-200 p-4 text-sm text-gray-600">
            Preview tidak tersedia untuk tipe file ini.
        </div>
    @endif

    <div class="flex items-center gap-2">
        <a
            href="{{ $previewUrl }}"
            target="_blank"
            class="inline-flex items-center rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
        >
            Buka di tab baru
        </a>
    </div>
</div>
