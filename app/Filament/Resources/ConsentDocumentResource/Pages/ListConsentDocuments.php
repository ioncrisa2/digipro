<?php

namespace App\Filament\Resources\ConsentDocumentResource\Pages;

use App\Filament\Resources\ConsentDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConsentDocuments extends ListRecords
{
    protected static string $resource = ConsentDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
