<?php

namespace App\Filament\Reviewer\Resources;

use App\Enums\AppraisalStatusEnum;
use App\Filament\Reviewer\Resources\AppraisalComparableResource\Pages;
use App\Models\AppraisalAsset;
use App\Models\AppraisalAssetComparable;
use App\Models\AdjustmentFactor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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
                                modifyQueryUsing: fn(Builder $query) => $query
                                    ->with('request')
                                    ->whereHas('request', fn(Builder $requestQuery) => $requestQuery
                                        ->whereIn('status', [
                                            AppraisalStatusEnum::ContractSigned->value,
                                            AppraisalStatusEnum::ValuationOnProgress->value,
                                            AppraisalStatusEnum::ValuationCompleted->value,
                                        ]))
                            )
                            ->getOptionLabelFromRecordUsing(fn(AppraisalAsset $record): string => self::assetOptionLabel($record))
                            ->searchable()
                            ->optionsLimit(20) // optional, biar tidak “kebanyakan”
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

                        Toggle::make('is_selected')
                            ->label('Gunakan untuk penyesuaian')
                            ->inline(false),

                        Forms\Components\TextInput::make('manual_rank')
                            ->label('Rank Manual')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Kosongkan untuk pakai priority rank dari API.'),

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

                        Forms\Components\TextInput::make('distance_meters')
                            ->label('Jarak (m)')
                            ->numeric()
                            ->step(0.01),
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

                        Forms\Components\TextInput::make('image_url')
                            ->label('Image URL')
                            ->maxLength(255)
                            ->helperText('Otomatis dari API jika ada.')
                            ->disabled(fn($record) => (bool) $record?->exists),
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
                                    ->options(fn() => AdjustmentFactor::query()
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
                            ->visible(fn(string $operation) => $operation === 'edit') // hanya edit
                            ->formatStateUsing(fn($state) => self::formatJson($state))
                            ->rules(['nullable', 'json'])
                            ->dehydrateStateUsing(fn($state) => blank($state) ? null : json_decode((string) $state, true))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['asset.request']))
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Foto')
                    ->height(64)
                    ->width(96)
                    ->extraImgAttributes(['class' => 'object-cover rounded-md'])
                    ->defaultImageUrl('https://ui-avatars.com/api/?name=PB'),

                Tables\Columns\TextColumn::make('asset.request.request_number')
                    ->label('No. Permohonan')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('asset.address')
                    ->label('Aset')
                    ->limit(80)
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('appraisal_asset_id')
                    ->label('ID Aset')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('external_id')
                    ->label('Ext ID')
                    ->badge()
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_selected')
                    ->label('Pakai')
                    ->boolean()
                    ->action(fn(AppraisalAssetComparable $record) => $record->update(['is_selected' => ! $record->is_selected])),

                Tables\Columns\TextColumn::make('score')
                    ->label('Score')
                    ->numeric(decimalPlaces: 3)
                    ->sortable(),

                Tables\Columns\TextColumn::make('distance_meters')
                    ->label('Jarak (m)')
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),

                Tables\Columns\TextColumn::make('manual_rank')
                    ->label('Rank')
                    ->placeholder('-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('raw_land_area')
                    ->label('Luas (m²)')
                    ->numeric(decimalPlaces: 0)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('raw_data_date')
                    ->label('Tgl Data')
                    ->date(),

                Tables\Columns\TextColumn::make('raw_peruntukan')
                    ->label('Peruntukan'),

                Tables\Columns\TextColumn::make('indication_value')
                    ->label('Indikasi')
                    ->money('idr')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('appraisal_asset_id')
                    ->label('Aset Subjek')
                    ->relationship('asset', 'address')
                    ->searchable()
                    ->preload()
                    ->default(fn() => request()->query('asset_id')),
                TernaryFilter::make('is_selected')
                    ->label('Dipakai')
                    ->boolean(),
                SelectFilter::make('manual_rank')
                    ->label('Rank Manual')
                    ->options(range(1, 10))
                    ->placeholder('Semua'),
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
                            ->map(fn($value) => (int) $value)
                            ->filter(fn(int $value) => $value > 0)
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
                Tables\Actions\Action::make('toggleSelect')
                    ->visible(false)
                    ->action(function (AppraisalAssetComparable $record) {
                        $record->update(['is_selected' => ! $record->is_selected]);
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('selectAll')
                    ->label('Tandai Dipakai')
                    ->action(fn($records) => $records->each->update(['is_selected' => true])),
                Tables\Actions\BulkAction::make('deselectAll')
                    ->label('Hapus Tanda')
                    ->color('secondary')
                    ->action(fn($records) => $records->each->update(['is_selected' => false])),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfoSection::make('Media')
                ->schema([
                    ImageEntry::make('image_url')
                        ->label('Foto')
                        ->height(180)
                        ->extraImgAttributes(['class' => 'object-cover rounded-lg'])
                        ->default('https://ui-avatars.com/api/?name=PB'),
                ]),

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
                        ->state(fn(AppraisalAssetComparable $record): int => $record->landAdjustments->count()),
                ]),

            InfoSection::make('Detail API')
                ->columns(2)
                ->schema([
                    TextEntry::make('snapshot_json.jenis_listing.name')->label('Jenis Listing'),
                    TextEntry::make('snapshot_json.peruntukan.name')->label('Peruntukan'),
                    TextEntry::make('snapshot_json.jenis_objek.name')->label('Jenis Objek'),
                    TextEntry::make('snapshot_json.dokumen_tanah.name')->label('Dokumen Tanah'),
                    TextEntry::make('snapshot_json.alamat_data')->label('Alamat Data')->columnSpanFull(),
                    TextEntry::make('snapshot_json.province.name')->label('Provinsi'),
                    TextEntry::make('snapshot_json.regency.name')->label('Kab/Kota'),
                    TextEntry::make('snapshot_json.district.name')->label('Kecamatan'),
                    TextEntry::make('snapshot_json.village.name')->label('Kelurahan'),
                    TextEntry::make('snapshot_json')
                        ->label('Koordinat')
                        ->state(fn($state) => ($lat = data_get($state, 'latitude')) && ($lng = data_get($state, 'longitude'))
                            ? $lat . ', ' . $lng
                            : '-'),
                    TextEntry::make('snapshot_json.luas_tanah')->label('Luas Tanah'),
                    TextEntry::make('snapshot_json.luas_bangunan')->label('Luas Bangunan'),
                    TextEntry::make('snapshot_json.tanggal_data')->label('Tanggal Data'),
                    TextEntry::make('snapshot_json.harga')
                        ->label('Harga')
                        ->formatStateUsing(fn($state) => $state ? 'Rp ' . number_format((int) $state, 0, ',', '.') : '-'),
                ]),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['asset.request', 'landAdjustments']) // ← add this
            ->whereHas('asset.request', fn(Builder $query) => $query->whereIn('status', [
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
