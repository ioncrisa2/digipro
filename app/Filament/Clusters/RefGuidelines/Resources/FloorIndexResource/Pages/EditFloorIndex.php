<?php

namespace App\Filament\Clusters\RefGuidelines\Resources\FloorIndexResource\Pages;

use App\Filament\Clusters\RefGuidelines\Resources\FloorIndexResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFloorIndex extends EditRecord
{
    protected static string $resource = FloorIndexResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
