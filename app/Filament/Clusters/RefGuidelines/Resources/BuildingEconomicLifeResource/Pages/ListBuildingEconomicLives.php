<?php

namespace App\Filament\Clusters\RefGuidelines\Resources\BuildingEconomicLifeResource\Pages;

use App\Filament\Clusters\RefGuidelines\Resources\BuildingEconomicLifeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBuildingEconomicLives extends ListRecords
{
    protected static string $resource = BuildingEconomicLifeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
