<?php

namespace App\Filament\Reviewer\Resources\AppraisalReviewResource\RelationManagers;

use App\Enums\AssetTypeEnum;
use App\Filament\Reviewer\Pages\AdjustmentWorkbench;
use App\Models\AppraisalAsset;
use App\Services\ComparableDataApi;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\View as ViewComponent;
use Filament\Forms\Components\Hidden;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\AppraisalAssetComparable;
use Illuminate\Support\Arr;
use Filament\Forms\Components\ViewField;

class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    protected static ?string $title = 'Objek Penilaian';

    private array $itemsCache = [];

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('address')
            ->defaultSort('id')
            ->columns([
                Tables\Columns\BadgeColumn::make('asset_type')
                    ->label('Jenis Aset')
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') {
                            return '-';
                        }

                        $value = is_string($state) ? $state : (string) $state;
                        return AssetTypeEnum::tryFrom($value)?->label() ?? $value;
                    }),

                Tables\Columns\TextColumn::make('address')
                    ->label('Alamat')
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('land_area')
                    ->label('Luas Tanah')
                    ->suffix(' m2')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('building_area')
                    ->label('Luas Bangunan')
                    ->suffix(' m2')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('estimated_value_low')
                    ->label('Estimasi Bawah')
                    ->money('idr')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('estimated_value_high')
                    ->label('Estimasi Atas')
                    ->money('idr')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('market_value_final')
                    ->label('Nilai Tengah')
                    ->money('idr')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('coordinates')
                    ->label('Koordinat')
                    ->state(fn($record) => filled($record->coordinates_lat) && filled($record->coordinates_lng)
                        ? "{$record->coordinates_lat}, {$record->coordinates_lng}"
                        : '-'),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('openAdjustmentWorkbench')
                    ->label('Adjustment Matrix')
                    ->icon('heroicon-o-table-cells')
                    ->color('warning')
                    ->url(fn (AppraisalAsset $record): string => AdjustmentWorkbench::getUrl([
                        'asset' => $record->id,
                    ])),
                Tables\Actions\Action::make('searchComparables')
                    ->label('Cari & Pilih Pembanding')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('primary')
                    ->slideOver()
                    ->form([
                        Forms\Components\TextInput::make('range_km')
                            ->label('Range (km)')
                            ->numeric()
                            ->minValue(0.1)
                            ->maxValue(100)
                            ->default(config('comparable.default_range_km', 10)),

                        Forms\Components\TextInput::make('limit')
                            ->label('Limit')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(200)
                            ->default(config('comparable.default_limit', 100)),

                        Hidden::make('selected_ids')
                            ->default([]),

                        ViewField::make('selected_ids')
                            ->label('Pilih Pembanding')
                            ->default([])
                            ->view('filament.reviewer.comparables.search-select')
                            ->viewData(function (callable $get, AppraisalAsset $record) {
                                $items = $this->fetchComparableItems(
                                    $record,
                                    (int) ($get('limit') ?? config('comparable.default_limit', 100)),
                                    (float) ($get('range_km') ?? config('comparable.default_range_km', 10)),
                                );

                                return [
                                    'items' => $items,
                                ];
                            }),
                    ])
                    ->action(function (array $data, AppraisalAsset $record): void {
                        if (! $record->coordinates_lat || ! $record->coordinates_lng) {
                            Notification::make()->title('Koordinat belum lengkap')->danger()->send();
                            return;
                        }

                        if (! $record->district_id || ! $record->peruntukan) {
                            Notification::make()->title('Data lokasi/peruntukan kosong')->danger()->send();
                            return;
                        }

                        $selected = $data['selected_ids'] ?? [];
                        if (empty($selected)) {
                            Notification::make()->title('Belum ada pembanding dipilih')->warning()->send();
                            return;
                        }

                        $limit = (int) ($data['limit'] ?? config('comparable.default_limit', 100));
                        $rangeKm = (float) ($data['range_km'] ?? config('comparable.default_range_km', 10));

                        try {
                            $items = collect($this->fetchComparableItems($record, $limit, $rangeKm))
                                ->whereIn('id', $selected)
                                ->values()
                                ->all();

                            $service = app(ComparableDataApi::class); // ← was missing, caused undefined variable error

                            AppraisalAssetComparable::where('appraisal_asset_id', $record->id)->delete();

                            $created = $service->upsertComparables($record, $items);

                            Notification::make()
                                ->title('Pembanding disimpan')
                                ->body("Total disimpan: {$created}.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Gagal menyimpan pembanding')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
            ])
            ->bulkActions([]);
    }

    private function fetchComparableItems(AppraisalAsset $record, int $limit, float $rangeKm): array
    {
        $cacheKey = $record->id . '|' . $limit . '|' . $rangeKm;
        if (isset($this->itemsCache[$cacheKey])) {
            return $this->itemsCache[$cacheKey];
        }

        if (! $record->coordinates_lat || ! $record->coordinates_lng || ! $record->district_id || ! $record->peruntukan) {
            return [];
        }

        $service = app(ComparableDataApi::class);
        $items = $service->fetchSimilarForAsset($record, $limit, $rangeKm);
        $this->itemsCache[$cacheKey] = $items;

        return $items;
    }
}
