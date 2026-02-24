<?php

namespace App\Filament\Clusters\RefGuidelines\Resources\RefGuidelineSetResource\Pages;

use Filament\Actions;
use App\Models\GuidelineSet;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Clusters\RefGuidelines\Resources\RefGuidelineSetResource;

class CreateRefGuidelineSet extends CreateRecord
{
    protected static string $resource = RefGuidelineSetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!empty($data['is_active'])) {
            GuidelineSet::query()->update(['is_active' => false]);
        }
        return $data;
    }
}
