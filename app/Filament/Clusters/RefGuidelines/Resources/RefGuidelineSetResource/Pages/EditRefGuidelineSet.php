<?php

namespace App\Filament\Clusters\RefGuidelines\Resources\RefGuidelineSetResource\Pages;

use Filament\Actions;
use App\Models\GuidelineSet;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Clusters\RefGuidelines\Resources\RefGuidelineSetResource;

class EditRefGuidelineSet extends EditRecord
{
    protected static string $resource = RefGuidelineSetResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['is_active'])) {
            GuidelineSet::query()
                ->where('id', '!=', $this->record->id)
                ->update(['is_active' => false]);
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
