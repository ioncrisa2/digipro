<?php

namespace App\Filament\Reviewer\Resources;

use App\Enums\AppraisalStatusEnum;
use App\Filament\Reviewer\Resources\AppraisalComparableResource\Pages;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetComparable;
use App\Models\AdjustmentFactor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class AppraisalComparableResource extends Resource
{
    protected static ?string $model = AppraisalAssetComparable::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Penilaian';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Objek Pembanding')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('appraisal_asset_id')
                            ->label('Aset Subjek')
                            ->relationship(
                                name: 'asset',
                                titleAttribute: 'address',
                                modifyQueryUsing: fn (Builder $query) => $query
                                    ->with('request')
                                    ->whereHas('request', fn (Builder $requestQuery) => $requestQuery
                                        ->whereIn('status', [
                                            AppraisalStatusEnum::ContractSigned->value,
                                            AppraisalStatusEnum::ValuationOnProgress->value,
                                            AppraisalStatusEnum::ValuationCompleted->value,
                                        ]))
                            )
                            ->getOptionLabelFromRecordUsing(fn (AppraisalAsset $record): string => self::assetOptionLabel($record))
                            ->searchable()
                            ->preload()
                            ->required(),

                        Forms\Components\TextInput::make('external_id')
                            ->label('External ID')
                            ->numeric()
                            ->required(),

                        Forms\Components\TextInput::make('external_source')
                            ->label('Sumber Data')
                            ->default('pembanding_service')
                            ->maxLength(100)
                            ->required(),

                        Forms\Components\TextInput::make('rank')
                            ->label('Peringkat')
                            ->numeric()
                            ->minValue(1),

                        Forms\Components\TextInput::make('score')
                            ->label('Skor Kemiripan')
                            ->numeric()
                            ->step(0.0001),

                        Forms\Components\TextInput::make('weight')
                            ->label('Bobot')
                            ->numeric()
                            ->step(0.0001),
                    ]),

                Forms\Components\Section::make('Data Mentah Pembanding')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('raw_price')
                            ->label('Harga Raw')
                            ->numeric()
                            ->prefix('Rp'),

                        Forms\Components\TextInput::make('raw_land_area')
                            ->label('Luas Tanah Raw (m2)')
                            ->numeric()
                            ->step(0.01),

                        Forms\Components\TextInput::make('raw_building_area')
                            ->label('Luas Bangunan Raw (m2)')
                            ->numeric()
                            ->step(0.01),

                        Forms\Components\TextInput::make('raw_unit_price_land')
                            ->label('Harga/m2 Tanah Raw')
                            ->numeric()
                            ->prefix('Rp'),

                        Forms\Components\TextInput::make('raw_peruntukan')
                            ->label('Peruntukan Raw')
                            ->maxLength(100),

                        Forms\Components\DatePicker::make('raw_data_date')
                            ->label('Tanggal Data Raw'),
                    ]),

                Forms\Components\Section::make('Hasil Penyesuaian')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('total_adjustment_percent')
                            ->label('Total Adjustment (%)')
                            ->numeric()
                            ->step(0.0001),

                        Forms\Components\TextInput::make('adjusted_unit_value')
                            ->label('Adjusted Unit Value')
                            ->numeric()
                            ->prefix('Rp'),

                        Forms\Components\TextInput::make('indication_value')
                            ->label('Indication Value')
                            ->numeric()
                            ->prefix('Rp'),
                    ]),

                Forms\Components\Section::make('Rincian Adjustment')
                    ->schema([
                        Forms\Components\Repeater::make('landAdjustments')
                            ->relationship()
                            ->label('Adjustment Factors')
                            ->defaultItems(0)
                            ->collapsed()
                            ->itemLabel(function (array $state): ?string {
                                $percent = data_get($state, 'adjustment_percent');
                                $factorId = data_get($state, 'factor_id');
                                return $factorId
                                    ? "Factor #{$factorId}" . ($percent !== null ? " ({$percent}%)" : '')
                                    : 'Adjustment Baru';
                            })
                            ->schema([
                                Forms\Components\Select::make('factor_id')
                                    ->label('Factor')
                                    ->options(fn () => AdjustmentFactor::query()
                                        ->where('is_active', true)
                                        ->orderBy('sort_order')
                                        ->pluck('name', 'id')
                                        ->all())
                                    ->searchable()
                                    ->required(),

                                Forms\Components\TextInput::make('subject_value')
                                    ->label('Subject Value')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('comparable_value')
                                    ->label('Comparable Value')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('adjustment_percent')
                                    ->label('Adjustment (%)')
                                    ->numeric()
                                    ->step(0.0001),

                                Forms\Components\TextInput::make('adjustment_amount')
                                    ->label('Adjustment Amount')
                                    ->numeric()
                                    ->prefix('Rp'),

                                Forms\Components\Textarea::make('note')
                                    ->label('Catatan')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Snapshot API')
                    ->schema([
                        Forms\Components\Textarea::make('snapshot_json')
                            ->label('Snapshot JSON')
                            ->rows(6)
                            ->formatStateUsing(fn ($state) => self::formatJson($state))
                            ->rules(['nullable', 'json'])
                            ->dehydrateStateUsing(fn ($state) => blank($state) ? null : json_decode((string) $state, true))
                            ->helperText('Opsional. Simpan payload pembanding dari API internal.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset.request']))
            ->columns([
                Tables\Columns\TextColumn::make('asset.request.request_number')
                    ->label('No. Permohonan')
                    ->searchable(),

                Tables\Columns\TextColumn::make('asset.address')
                    ->label('Aset')
                    ->limit(45)
                    ->searchable(),

                Tables\Columns\TextColumn::make('external_id')
                    ->label('External ID')
                    ->searchable(),

                Tables\Columns\TextColumn::make('rank')
                    ->label('Rank')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('total_adjustment_percent')
                    ->label('Adj (%)')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('adjusted_unit_value')
                    ->label('Adjusted Unit')
                    ->money('idr')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('indication_value')
                    ->label('Indikasi')
                    ->money('idr')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('appraisal_asset_id')
                    ->label('Aset Subjek')
                    ->relationship('asset', 'address')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),

                Tables\Actions\Action::make('syncAssetRange')
                    ->label('Sync Range Aset')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (AppraisalAssetComparable $record): void {
                        $asset = $record->asset;
                        if (! $asset) {
                            Notification::make()
                                ->title('Aset tidak ditemukan')
                                ->danger()
                                ->send();
                            return;
                        }

                        $values = $asset->comparables()
                            ->whereNotNull('indication_value')
                            ->pluck('indication_value')
                            ->map(fn ($value) => (int) $value)
                            ->filter(fn (int $value) => $value > 0)
                            ->values();

                        if ($values->isEmpty()) {
                            Notification::make()
                                ->title('Belum ada indication value')
                                ->body('Isi minimal satu nilai indikasi pembanding sebelum sync range.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $low = (int) $values->min();
                        $high = (int) $values->max();
                        $mid = (int) round($values->avg());

                        $asset->update([
                            'estimated_value_low' => $low,
                            'estimated_value_high' => $high,
                            'market_value_final' => $mid,
                        ]);

                        Notification::make()
                            ->title('Range aset tersinkron')
                            ->body('Low: Rp ' . number_format($low, 0, ',', '.') . ' | High: Rp ' . number_format($high, 0, ',', '.') . ' | Mid: Rp ' . number_format($mid, 0, ',', '.'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfoSection::make('Ringkasan Pembanding')
                ->columns(3)
                ->schema([
                    TextEntry::make('asset.request.request_number')->label('No. Permohonan'),
                    TextEntry::make('asset.address')->label('Aset'),
                    TextEntry::make('external_id')->label('External ID'),
                    TextEntry::make('external_source')->label('Sumber'),
                    TextEntry::make('rank')->label('Rank')->placeholder('-'),
                    TextEntry::make('score')->label('Score')->placeholder('-'),
                    TextEntry::make('weight')->label('Weight')->placeholder('-'),
                    TextEntry::make('total_adjustment_percent')->label('Total Adj (%)')->placeholder('-'),
                    TextEntry::make('indication_value')->label('Indication Value')->money('idr')->placeholder('-'),
                ]),

            InfoSection::make('Adjustment Factors')
                ->schema([
                    TextEntry::make('landAdjustments_count')
                        ->label('Total Adjustment Factor')
                        ->state(fn (AppraisalAssetComparable $record): int => $record->landAdjustments()->count()),
                ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('asset.request', fn (Builder $query) => $query->whereIn('status', [
                AppraisalStatusEnum::ContractSigned->value,
                AppraisalStatusEnum::ValuationOnProgress->value,
                AppraisalStatusEnum::ValuationCompleted->value,
            ]));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppraisalComparables::route('/'),
            'create' => Pages\CreateAppraisalComparable::route('/create'),
            'edit' => Pages\EditAppraisalComparable::route('/{record}/edit'),
            'view' => Pages\ViewAppraisalComparable::route('/{record}'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Data Pembanding';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Data Pembanding';
    }

    private static function assetOptionLabel(AppraisalAsset $asset): string
    {
        $requestNumber = $asset->request?->request_number ?? ('REQ-' . $asset->appraisal_request_id);
        $address = Str::limit((string) $asset->address, 60);

        return "{$requestNumber} | Aset #{$asset->id} | {$address}";
    }

    private static function formatJson(mixed $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        if (is_string($state)) {
            return $state;
        }

        return json_encode(
            $state,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: null;
    }
}
