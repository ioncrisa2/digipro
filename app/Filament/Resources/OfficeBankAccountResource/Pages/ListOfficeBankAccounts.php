<?php

namespace App\Filament\Resources\OfficeBankAccountResource\Pages;

use App\Filament\Resources\OfficeBankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOfficeBankAccounts extends ListRecords
{
    protected static string $resource = OfficeBankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
