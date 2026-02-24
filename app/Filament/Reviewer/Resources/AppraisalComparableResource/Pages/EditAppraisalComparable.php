<?php

namespace App\Filament\Reviewer\Resources\AppraisalComparableResource\Pages;

use App\Filament\Reviewer\Resources\AppraisalComparableResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAppraisalComparable extends EditRecord
{
    protected static string $resource = AppraisalComparableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
