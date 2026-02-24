<?php

namespace App\Filament\Resources\AppraisalUserConsentResource\Pages;

use App\Filament\Resources\AppraisalUserConsentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAppraisalUserConsent extends ViewRecord
{
    protected static string $resource = AppraisalUserConsentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
