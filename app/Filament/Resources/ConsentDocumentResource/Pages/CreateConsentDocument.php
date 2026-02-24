<?php

namespace App\Filament\Resources\ConsentDocumentResource\Pages;

use Filament\Actions;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\ConsentDocumentResource;

class CreateConsentDocument extends CreateRecord
{
    protected static string $resource = ConsentDocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();
        $data['hash'] = $data['hash'] ?? str_repeat('0', 64); // set on publish
        return $data;
    }
}
