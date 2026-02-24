<?php

namespace App\Filament\Clusters\RefGuidelines\Resources\ConstructionCostIndexResource\Pages;

use App\Filament\Clusters\RefGuidelines\Resources\ConstructionCostIndexResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConstructionCostIndex extends EditRecord
{
    protected static string $resource = ConstructionCostIndexResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
