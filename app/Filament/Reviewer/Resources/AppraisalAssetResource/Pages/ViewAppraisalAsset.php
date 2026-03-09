<?php

namespace App\Filament\Reviewer\Resources\AppraisalAssetResource\Pages;

use App\Filament\Reviewer\Pages\AdjustmentWorkbench;
use App\Filament\Reviewer\Resources\AppraisalAssetResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewAppraisalAsset extends ViewRecord
{
    protected static string $resource = AppraisalAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openAdjustmentWorkbench')
                ->label('Adjust Harga Tanah')
                ->icon('heroicon-o-table-cells')
                ->color('warning')
                ->url(fn (): string => AdjustmentWorkbench::getUrl([
                    'asset' => $this->getRecord()->getKey(),
                ])),
        ];
    }
}
