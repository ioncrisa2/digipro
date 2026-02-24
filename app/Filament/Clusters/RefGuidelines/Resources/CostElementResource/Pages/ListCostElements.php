<?php

namespace App\Filament\Clusters\RefGuidelines\Resources\CostElementResource\Pages;

use Filament\Actions;
use App\Models\GuidelineSet;
use Filament\Actions\Action;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Actions\ActionGroup;
use App\Exports\CostElementExport;
use App\Imports\CostElementImport;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\ActionSize;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Clusters\RefGuidelines\Resources\CostElementResource;

class ListCostElements extends ListRecords
{
    protected static string $resource = CostElementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ActionGroup::make([
                Action::make('Import Data BTB')
                    ->label('Import')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
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

                        // year auto dari guideline set, tapi bisa diganti
                        TextInput::make('year')
                            ->numeric()
                            ->required()
                            ->minValue(2000)
                            ->maxValue(2100)
                            ->default(fn() => GuidelineSet::query()->where('is_active', true)->value('year') ?? now()->year),

                        // base_region default DKI Jakarta, disabled tapi tetap submit
                        TextInput::make('base_region')
                            ->label('Base Region')
                            ->default('DKI Jakarta')
                            ->disabled()
                            ->dehydrated(true)
                            ->required(),

                        FileUpload::make('file')
                            ->label('File Excel/CSV')
                            ->required()
                            ->disk('local')
                            ->directory('imports')
                            ->acceptedFileTypes([
                                'text/csv',
                                'application/vnd.ms-excel',
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            ]),
                    ])
                    ->action(function (array $data) {
                        $path = Storage::disk('local')->path($data['file']);

                        $import = new CostElementImport(
                            (int) $data['guideline_set_id'],
                            (int) $data['year'],
                            (string) $data['base_region'],
                        );

                        Excel::import($import, $path);

                        Notification::make()
                            ->title('Import selesai')
                            ->body("Diproses: {$import->processed} baris. Dilewati: {$import->skipped} baris.")
                            ->success()
                            ->send();

                        $this->resetTable();
                    }),
                Action::make('exportExcel')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')->action(function () {
                        $query = $this->getFilteredTableQuery();

                        return Excel::download(
                            new CostElementExport(clone $query),
                            'ref_cost_elements.xlsx'
                        );
                    }),
            ])
                ->label('Import & Export')
                ->size(ActionSize::Medium)
                ->color('primary')
                ->button()
        ];
    }
}
