<x-filament::page>
    {{ $this->form }}

    @if (filled($this->province_id) && filled($this->guideline_set_id) && filled($this->year) && count($this->items))
        <div class="flex justify-end gap-2">
            <x-filament::button color="gray" icon="heroicon-o-arrow-path" wire:click="mountAction('resetIkk')"
                wire:loading.attr="disabled" wire:target="mountAction">
                Reset IKK
            </x-filament::button>

            <x-filament::button color="gray" icon="heroicon-o-x-mark" wire:click="mountAction('resetProvince')"
                wire:loading.attr="disabled" wire:target="mountAction">
                Reset Provinsi
            </x-filament::button>

            <x-filament::button icon="heroicon-o-check" wire:click="save" wire:loading.attr="disabled"
                wire:target="save">
                Simpan
            </x-filament::button>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament::page>
