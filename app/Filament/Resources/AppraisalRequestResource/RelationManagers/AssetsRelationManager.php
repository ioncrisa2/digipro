<?php

namespace App\Filament\Resources\AppraisalRequestResource\RelationManagers;

use App\Enums\AssetTypeEnum;
use App\Models\District;
use App\Models\Province;
use App\Models\Regency;
use App\Models\Village;
use App\Support\AppraisalAssetFieldOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    protected static ?string $recordTitleAttribute = 'asset_code';

    public static function getPluralLabel(): ?string
    {
        return 'Objek Penilaian';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Objek')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('asset_code')
                            ->label('Kode Objek (opsional)')
                            ->maxLength(50),

                        Forms\Components\Select::make('asset_type')
                            ->label('Jenis Aset')
                            ->options(AssetTypeEnum::options())
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('peruntukan')
                            ->label('Peruntukan')
                            ->options(AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::usageOptions()))
                            ->native(false)
                            ->searchable(),
                    ]),

                Forms\Components\Section::make('Data Umum Properti')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('title_document')
                            ->label('Dokumen Tanah')
                            ->options(AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::titleDocumentOptions()))
                            ->native(false)
                            ->searchable(),

                        Forms\Components\Select::make('land_shape')
                            ->label('Bentuk Tanah')
                            ->options(AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::landShapeOptions()))
                            ->native(false)
                            ->searchable(),

                        Forms\Components\Select::make('land_position')
                            ->label('Posisi Tanah')
                            ->options(AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::landPositionOptions()))
                            ->native(false)
                            ->searchable(),

                        Forms\Components\Select::make('land_condition')
                            ->label('Kondisi Tanah')
                            ->options(AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::landConditionOptions()))
                            ->native(false)
                            ->searchable(),

                        Forms\Components\Select::make('topography')
                            ->label('Topografi')
                            ->options(AppraisalAssetFieldOptions::toSelectMap(AppraisalAssetFieldOptions::topographyOptions()))
                            ->native(false)
                            ->searchable(),

                        Forms\Components\TextInput::make('frontage_width')
                            ->label('Lebar Muka (meter)')
                            ->numeric()
                            ->minValue(0),

                        Forms\Components\TextInput::make('access_road_width')
                            ->label('Lebar Akses Jalan (meter)')
                            ->numeric()
                            ->minValue(0),
                    ]),

                Forms\Components\Section::make('Lokasi')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->label('Alamat Lengkap')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('province_id')
                            ->label('Provinsi')
                            ->options(fn () => Province::query()->orderBy('name')->pluck('name', 'id')->toArray())
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('regency_id', null);
                                $set('district_id', null);
                                $set('village_id', null);
                            }),

                        Forms\Components\Select::make('regency_id')
                            ->label('Kab/Kota')
                            ->options(function (Get $get) {
                                $provinceId = $get('province_id');
                                if (blank($provinceId)) return [];

                                return Regency::query()
                                    ->where('province_id', $provinceId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->disabled(fn (Get $get) => blank($get('province_id')))
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('district_id', null);
                                $set('village_id', null);
                            }),

                        Forms\Components\Select::make('district_id')
                            ->label('Kecamatan')
                            ->options(function (Get $get) {
                                $regencyId = $get('regency_id');
                                if (blank($regencyId)) return [];

                                return District::query()
                                    ->where('regency_id', $regencyId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->disabled(fn (Get $get) => blank($get('regency_id')))
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('village_id', null);
                            }),

                        Forms\Components\Select::make('village_id')
                            ->label('Kelurahan/Desa')
                            ->options(function (Get $get) {
                                $districtId = $get('district_id');
                                if (blank($districtId)) return [];

                                return Village::query()
                                    ->where('district_id', $districtId)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->disabled(fn (Get $get) => blank($get('district_id'))),

                        Forms\Components\TextInput::make('coordinates_lat')
                            ->label('Latitude')
                            ->numeric()
                            ->step(0.0000001),

                        Forms\Components\TextInput::make('coordinates_lng')
                            ->label('Longitude')
                            ->numeric()
                            ->step(0.0000001),
                    ]),

                Forms\Components\Section::make('Luas & Bangunan')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('land_area')
                            ->label('Luas Tanah (m²)')
                            ->numeric()
                            ->minValue(0),

                        Forms\Components\TextInput::make('building_area')
                            ->label('Luas Bangunan (m²)')
                            ->numeric()
                            ->minValue(0),

                        Forms\Components\TextInput::make('building_floors')
                            ->label('Jumlah Lantai')
                            ->numeric()
                            ->minValue(0),

                        Forms\Components\TextInput::make('build_year')
                            ->label('Tahun Bangun')
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue(intval(date('Y')) + 1),

                        Forms\Components\TextInput::make('renovation_year')
                            ->label('Tahun Renovasi')
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue(intval(date('Y')) + 1),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('address')
            ->columns([
                Tables\Columns\TextColumn::make('asset_type')
                    ->label('Jenis Aset')
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') return '-';
                        if ($state instanceof AssetTypeEnum) return $state->label();
                        return AssetTypeEnum::from($state)->label();
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('address')
                    ->label('Alamat')
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\TextColumn::make('peruntukan')
                    ->label('Peruntukan')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('land_area')
                    ->label('Luas Tanah')
                    ->suffix(' m²'),

                Tables\Columns\TextColumn::make('building_area')
                    ->label('Luas Bangunan')
                    ->suffix(' m²')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('building_floors')
                    ->label('Lantai')
                    ->placeholder('-'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
