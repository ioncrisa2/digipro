<?php

namespace App\Filament\Reviewer\Resources\AppraisalComparableResource\Pages;

use App\Filament\Reviewer\Resources\AppraisalComparableResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAppraisalComparable extends ViewRecord
{
    protected static string $resource = AppraisalComparableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
