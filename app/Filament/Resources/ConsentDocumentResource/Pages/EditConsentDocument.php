<?php

namespace App\Filament\Resources\ConsentDocumentResource\Pages;

use Filament\Actions;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\ConsentDocumentResource;

class EditConsentDocument extends EditRecord
{
    protected static string $resource = ConsentDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::id();
        return $data;
    }
}
