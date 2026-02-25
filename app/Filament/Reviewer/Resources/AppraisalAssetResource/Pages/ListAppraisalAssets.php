<?php

namespace App\Filament\Reviewer\Resources\AppraisalAssetResource\Pages;

use App\Filament\Reviewer\Resources\AppraisalAssetResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAppraisalAssets extends ListRecords
{
    protected static string $resource = AppraisalAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
