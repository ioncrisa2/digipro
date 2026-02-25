<?php

namespace App\Filament\Reviewer\Resources\AppraisalAssetResource\RelationManagers;

use App\Models\AppraisalAssetComparable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ComparablesRelationManager extends RelationManager
{
    protected static string $relationship = 'comparables';
    protected static ?string $title = 'Pembanding';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Foto')
                    ->height(48)
                    ->width(72)
                    ->extraImgAttributes(['class' => 'object-cover rounded-md'])
                    ->defaultImageUrl('https://ui-avatars.com/api/?name=PB'),

                Tables\Columns\TextColumn::make('external_id')->label('Ext ID')->badge()->sortable(),

                Tables\Columns\IconColumn::make('is_selected')
                    ->label('Pakai')
                    ->boolean()
                    ->action(fn (AppraisalAssetComparable $record) => $record->update(['is_selected' => ! $record->is_selected])),

                Tables\Columns\TextColumn::make('score')->numeric(decimalPlaces: 3)->sortable(),
                Tables\Columns\TextColumn::make('distance_meters')->label('Jarak (m)')->numeric(decimalPlaces: 1)->sortable(),
                Tables\Columns\TextColumn::make('manual_rank')->label('Rank')->placeholder('-')->sortable(),
                Tables\Columns\TextColumn::make('indication_value')->label('Indikasi')->money('idr')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_selected')->label('Dipakai')->boolean(),
                Tables\Filters\SelectFilter::make('manual_rank')->label('Rank Manual')->options(range(1, 10))->placeholder('Semua'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }
}
