<?php

namespace App\Filament\Clusters\DaftarNamaLokasi\Resources\RegencyResource\Pages;

use App\Filament\Clusters\DaftarNamaLokasi\Resources\RegencyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRegencies extends ListRecords
{
    protected static string $resource = RegencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
