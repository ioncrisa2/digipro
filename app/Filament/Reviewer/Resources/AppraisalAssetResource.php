<?php

namespace App\Filament\Reviewer\Resources;

use App\Enums\AppraisalStatusEnum;
use App\Filament\Reviewer\Resources\AppraisalAssetResource\Pages;
use App\Filament\Reviewer\Resources\AppraisalAssetResource\RelationManagers\ComparablesRelationManager;
use App\Models\AppraisalAsset;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppraisalAssetResource extends Resource
{
    protected static ?string $model = AppraisalAsset::class;

    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationGroup = 'Penilaian';
    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(function (Builder $query) {
                return $query
                    ->with(['request'])
                    ->withCount([
                        'comparables',
                        'comparables as selected_comparables_count' => fn (Builder $q) => $q->where('is_selected', true),
                    ])
                    ->whereHas('request', fn (Builder $q) => $q->whereIn('status', [
                        AppraisalStatusEnum::ContractSigned->value,
                        AppraisalStatusEnum::ValuationOnProgress->value,
                        AppraisalStatusEnum::ValuationCompleted->value,
                    ]));
            })
            ->columns([
                Tables\Columns\TextColumn::make('request.request_number')
                    ->label('No. Permohonan')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('address')
                    ->label('Alamat Aset')
                    ->limit(80)
                    ->wrap()
                    ->searchable(),

                Tables\Columns\TextColumn::make('comparables_count')
                    ->label('Total Pembanding')
                    ->sortable(),

                Tables\Columns\TextColumn::make('selected_comparables_count')
                    ->label('Dipakai')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(), // di halaman view akan muncul tab relation pembanding
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ComparablesRelationManager::class,
        ];
    }

    public static function canCreate(): bool
    {
        return false; // aset biasanya dibuat dari proses permohonan, bukan manual di sini
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppraisalAssets::route('/'),
            'view'  => Pages\ViewAppraisalAsset::route('/{record}'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Aset';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Aset';
    }
}
