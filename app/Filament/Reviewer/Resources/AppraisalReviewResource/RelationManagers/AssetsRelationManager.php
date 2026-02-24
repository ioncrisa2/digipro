<?php

namespace App\Filament\Reviewer\Resources\AppraisalReviewResource\RelationManagers;

use App\Enums\AssetTypeEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    protected static ?string $title = 'Objek Penilaian';

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
                    ->state(fn ($record) => filled($record->coordinates_lat) && filled($record->coordinates_lng)
                        ? "{$record->coordinates_lat}, {$record->coordinates_lng}"
                        : '-'),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
