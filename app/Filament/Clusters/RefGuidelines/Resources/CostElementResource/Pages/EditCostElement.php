<?php

namespace App\Filament\Clusters\RefGuidelines\Resources\CostElementResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Nben\FilamentRecordNav\Actions\NextRecordAction;
use Nben\FilamentRecordNav\Actions\PreviousRecordAction;
use Nben\FilamentRecordNav\Concerns\WithRecordNavigation;
use App\Filament\Clusters\RefGuidelines\Resources\CostElementResource;

class EditCostElement extends EditRecord
{
    // use WithRecordNavigation;

    protected static string $resource = CostElementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            // PreviousRecordAction::make(),
            // NextRecordAction::make(),
        ];
    }
}
