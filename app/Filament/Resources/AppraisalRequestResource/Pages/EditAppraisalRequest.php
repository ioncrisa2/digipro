<?php

namespace App\Filament\Resources\AppraisalRequestResource\Pages;

use App\Filament\Resources\AppraisalRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAppraisalRequest extends EditRecord
{
    protected static string $resource = AppraisalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
