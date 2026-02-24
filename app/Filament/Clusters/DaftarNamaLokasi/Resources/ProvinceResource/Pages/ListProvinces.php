<?php

namespace App\Filament\Clusters\DaftarNamaLokasi\Resources\ProvinceResource\Pages;

use App\Filament\Clusters\DaftarNamaLokasi\Resources\ProvinceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProvinces extends ListRecords
{
    protected static string $resource = ProvinceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
