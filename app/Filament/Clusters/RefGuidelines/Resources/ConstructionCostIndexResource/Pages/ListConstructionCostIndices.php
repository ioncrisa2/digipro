<?php

namespace App\Filament\Clusters\RefGuidelines\Resources\ConstructionCostIndexResource\Pages;

use App\Exports\IkkExport;
use Filament\Actions;
use App\Imports\IKKImport;
use App\Models\GuidelineSet;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Maatwebsite\Excel\Facades\Excel;
use App\Filament\Pages\IkkByProvince;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\ActionSize;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Filament\Clusters\RefGuidelines\Resources\ConstructionCostIndexResource;

class ListConstructionCostIndices extends ListRecords
{
    protected static string $resource = ConstructionCostIndexResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus')
                ->label('Tambah Data Baru'),
            Action::make('bulkIkkByProvince')
                ->label('Input IKK per Provinsi')
                ->icon('heroicon-o-table-cells')
                ->url(fn() => IkkByProvince::getUrl()),
            ActionGroup::make([
                Action::make('importIkk')
                    ->label('Import Data IKK')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Select::make('guideline_set_id')
                            ->label('Guideline Set')
                            ->options(
                                fn() => GuidelineSet::query()
                                    ->orderByDesc('year')
                                    ->pluck('name', 'id')
                                    ->toArray()
                            )
                            ->default(fn() => GuidelineSet::query()->where('is_active', true)->value('id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('year', GuidelineSet::find($state)?->year);
                            }),

                        TextInput::make('year')
                            ->label('Year')
                            ->numeric()
                            ->required()
                            ->minValue(2000)
                            ->maxValue(2100)
                            ->helperText('Boleh kamu ubah (tidak harus sama dengan tahun guideline set).'),

                        FileUpload::make('file')
                            ->label('File Excel')
                            ->required()
                            ->storeFile(false)
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                                'text/csv',
                            ])
                    ])->action(function (array $data) {
                        $file = $data['file'] ?? null;

                        if (! $file instanceof TemporaryUploadedFile) {
                            Notification::make()
                                ->title('File tidak valid')
                                ->danger()
                                ->send();
                            return;
                        }

                        $guidelineSetId = (int) $data['guideline_set_id'];
                        $year = (int) $data['year'];

                        $import = new IKKImport(
                            guidelineSetId: $guidelineSetId,
                            year: $year,
                            skipProvinceRows: true,
                            requireRegency: true,
                        );

                        // ✅ import dari temp path
                        Excel::import($import, $file->getRealPath());

                        Notification::make()
                            ->title('Import IKK selesai')
                            ->body("Inserted: {$import->inserted} | Updated: {$import->updated} | Skipped: {$import->skipped}")
                            ->success()
                            ->send();
                    }),

                Action::make('exportIkk')
                    ->label('Export Data IKK')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->form([
                        Select::make('guideline_set_id')
                            ->label('Guideline Set')
                            ->options(fn() => GuidelineSet::query()->orderByDesc('year')->pluck('name', 'id')->toArray())
                            ->default(fn() => GuidelineSet::query()->where('is_active', true)->value('id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('year', GuidelineSet::find($state)?->year);
                            }),

                        TextInput::make('year')
                            ->label('Year')
                            ->numeric()
                            ->required()
                            ->minValue(2000)
                            ->maxValue(2100),
                    ])
                    ->action(function (array $data) {
                        $setId = (int) $data['guideline_set_id'];
                        $year  = (int) $data['year'];

                        $filename = "IKK_{$year}_set{$setId}.xlsx";

                        return Excel::download(new IkkExport($setId, $year), $filename);
                    }),

            ])->label('Import & Export')
                ->icon('heroicon-m-ellipsis-vertical')
                ->size(ActionSize::Medium)
                ->color('primary')
                ->button()

        ];
    }
}
