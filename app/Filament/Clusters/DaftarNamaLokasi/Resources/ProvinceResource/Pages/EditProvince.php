<?php

namespace App\Filament\Clusters\DaftarNamaLokasi\Resources\ProvinceResource\Pages;

use App\Filament\Clusters\DaftarNamaLokasi\Resources\ProvinceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProvince extends EditRecord
{
    protected static string $resource = ProvinceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
