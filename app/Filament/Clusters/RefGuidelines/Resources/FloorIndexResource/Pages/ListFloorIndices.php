<?php

namespace App\Filament\Clusters\RefGuidelines\Resources\FloorIndexResource\Pages;

use App\Exports\FloorIndexExport;
use Filament\Actions;
use App\Models\GuidelineSet;
use App\Imports\FloorIndexImport;
use Filament\Actions\ActionGroup;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\ActionSize;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Clusters\RefGuidelines\Resources\FloorIndexResource;

class ListFloorIndices extends ListRecords
{
    protected static string $resource = FloorIndexResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus')
                ->label('Tambah Data Baru'),

            ActionGroup::make([
                 Actions\Action::make('importExcel')
                ->label('Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
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
                        ->numeric()
                        ->required()
                        ->minValue(2000)
                        ->maxValue(2100)
                        ->default(fn () => GuidelineSet::query()->where('is_active', true)->value('year') ?? now()->year),

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

                    $import = new FloorIndexImport(
                        (int) $data['guideline_set_id'],
                        (int) $data['year'],
                    );

                    Excel::import($import, $path);

                    Notification::make()
                        ->title('Import selesai')
                        ->body("Diproses: {$import->processed} baris. Dilewati: {$import->skipped} baris.")
                        ->success()
                        ->send();

                    $this->resetTable();
                }),

            Actions\Action::make('exportExcel')
                ->label('Export (Import Format)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $query = $this->getFilteredTableQuery();

                    return Excel::download(
                        new FloorIndexExport(clone $query),
                        'ref_floor_index_import_format.xlsx'
                    );
                }),
            ])
                ->label('Import & Export')
                ->icon('heroicon-m-ellipsis-vertical')
                ->size(ActionSize::Medium)
                ->color('primary')
                ->button()
        ];
    }
}
