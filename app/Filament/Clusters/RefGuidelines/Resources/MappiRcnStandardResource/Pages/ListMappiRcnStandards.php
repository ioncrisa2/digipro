<?php

namespace App\Filament\Clusters\RefGuidelines\Resources\MappiRcnStandardResource\Pages;

use App\Exports\MappiRcnStandardExport;
use App\Filament\Clusters\RefGuidelines\Resources\MappiRcnStandardResource;
use App\Imports\MappiRcnStandardImport;
use App\Models\GuidelineSet;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\ActionSize;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class ListMappiRcnStandards extends ListRecords
{
    protected static string $resource = MappiRcnStandardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->label('Tambah Data Baru'),
            ActionGroup::make([
                Action::make('importExcel')
                    ->label('Import Excel')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Select::make('guideline_set_id')
                            ->label('Guideline Set')
                            ->options(fn () => GuidelineSet::query()->orderByDesc('year')->pluck('name', 'id')->toArray())
                            ->default(fn () => GuidelineSet::query()->where('is_active', true)->value('id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('year', GuidelineSet::find($state)?->year)),
                        TextInput::make('year')
                            ->label('Year')
                            ->numeric()
                            ->required()
                            ->minValue(2000)
                            ->maxValue(2100),
                        TextInput::make('reference_region')
                            ->label('Reference Region')
                            ->default('DKI Jakarta')
                            ->disabled()
                            ->dehydrated(true)
                            ->required(),
                        FileUpload::make('file')
                            ->label('File Excel/CSV')
                            ->required()
                            ->storeFile(false)
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ]),
                    ])
                    ->action(function (array $data): void {
                        $file = $data['file'] ?? null;

                        if (! $file instanceof TemporaryUploadedFile) {
                            Notification::make()
                                ->title('File tidak valid')
                                ->danger()
                                ->send();
                            return;
                        }

                        $import = new MappiRcnStandardImport(
                            guidelineSetId: (int) $data['guideline_set_id'],
                            year: (int) $data['year'],
                            referenceRegion: (string) $data['reference_region'],
                        );

                        Excel::import($import, $file->getRealPath());

                        Notification::make()
                            ->title('Import standar RCN selesai')
                            ->body("Inserted: {$import->inserted} | Updated: {$import->updated} | Skipped: {$import->skipped}")
                            ->success()
                            ->send();
                    }),
                Action::make('exportExcel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->form([
                        Select::make('guideline_set_id')
                            ->label('Guideline Set')
                            ->options(fn () => GuidelineSet::query()->orderByDesc('year')->pluck('name', 'id')->toArray())
                            ->default(fn () => GuidelineSet::query()->where('is_active', true)->value('id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => $set('year', GuidelineSet::find($state)?->year)),
                        TextInput::make('year')
                            ->label('Year')
                            ->numeric()
                            ->required()
                            ->minValue(2000)
                            ->maxValue(2100),
                    ])
                    ->action(function (array $data) {
                        $setId = (int) $data['guideline_set_id'];
                        $year = (int) $data['year'];

                        return Excel::download(
                            new MappiRcnStandardExport($setId, $year),
                            "mappi_rcn_standards_{$year}_set{$setId}.xlsx"
                        );
                    }),
            ])
                ->label('Import & Export')
                ->icon('heroicon-m-ellipsis-vertical')
                ->size(ActionSize::Medium)
                ->color('primary')
                ->button(),
        ];
    }
}
