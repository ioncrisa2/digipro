<?php

namespace App\Filament\Clusters\RefGuidelines\Resources\ValuationSettingResource\Pages;

use App\Filament\Clusters\RefGuidelines\Resources\ValuationSettingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListValuationSettings extends ListRecords
{
    protected static string $resource = ValuationSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Konfigurasi'),
        ];
    }
}
