<?php

namespace App\Filament\Clusters\RefGuidelines\Resources\RefGuidelineSetResource\Pages;

use App\Filament\Clusters\RefGuidelines\Resources\RefGuidelineSetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRefGuidelineSets extends ListRecords
{
    protected static string $resource = RefGuidelineSetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
