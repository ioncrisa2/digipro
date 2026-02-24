<?php

namespace App\Filament\Clusters\RefGuidelines\Resources\BuildingEconomicLifeResource\Pages;

use App\Filament\Clusters\RefGuidelines\Resources\BuildingEconomicLifeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBuildingEconomicLife extends EditRecord
{
    protected static string $resource = BuildingEconomicLifeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
