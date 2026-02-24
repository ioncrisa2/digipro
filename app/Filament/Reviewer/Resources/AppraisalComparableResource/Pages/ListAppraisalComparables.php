<?php

namespace App\Filament\Reviewer\Resources\AppraisalComparableResource\Pages;

use App\Filament\Reviewer\Resources\AppraisalComparableResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAppraisalComparables extends ListRecords
{
    protected static string $resource = AppraisalComparableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
