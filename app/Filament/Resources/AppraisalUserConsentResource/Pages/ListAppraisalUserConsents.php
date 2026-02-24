<?php

namespace App\Filament\Resources\AppraisalUserConsentResource\Pages;

use App\Exports\AppraisalUserConsentExport;
use App\Filament\Resources\AppraisalUserConsentResource;
use Filament\Actions\Action;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Resources\Pages\ListRecords;

class ListAppraisalUserConsents extends ListRecords
{
    protected static string $resource = AppraisalUserConsentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $query = $this->getFilteredTableQuery();

                    return Excel::download(
                        new AppraisalUserConsentExport(clone $query),
                        'appraisal_user_consents.xlsx'
                    );
                }),
        ];
    }
}
