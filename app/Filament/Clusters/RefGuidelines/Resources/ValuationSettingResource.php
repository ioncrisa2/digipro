<?php

namespace App\Filament\Clusters\RefGuidelines\Resources;

use App\Filament\Clusters\RefGuidelines;
use App\Filament\Clusters\RefGuidelines\Resources\ValuationSettingResource\Pages;
use App\Models\GuidelineSet;
use App\Models\ValuationSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class ValuationSettingResource extends Resource
{
    protected static ?string $model = ValuationSetting::class;

    protected static ?string $navigationIcon = null;

    protected static ?string $cluster = RefGuidelines::class;

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Konfigurasi Perhitungan';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('guideline_set_id')
                ->label('Guideline Set')
                ->options(fn () => GuidelineSet::query()->orderByDesc('year')->pluck('name', 'id')->toArray())
                ->default(fn () => GuidelineSet::query()->where('is_active', true)->value('id'))
                ->searchable()
                ->preload()
                ->required()
                ->reactive()
                ->afterStateUpdated(fn ($state, callable $set) => $set('year', GuidelineSet::find($state)?->year)),

            Forms\Components\TextInput::make('year')
                ->label('Year')
                ->numeric()
                ->required()
                ->minValue(2000)
                ->maxValue(2100),

            Forms\Components\Select::make('key')
                ->label('Key')
                ->options(ValuationSetting::keyOptions())
                ->required()
                ->reactive()
                ->afterStateUpdated(fn ($state, callable $set) => $set('label', ValuationSetting::labelForKey($state)))
                ->rules([
                    fn (Forms\Get $get, $record) => Rule::unique('ref_valuation_settings', 'key')
                        ->where('guideline_set_id', $get('guideline_set_id'))
                        ->where('year', $get('year'))
                        ->ignore($record?->id),
                ]),

            Forms\Components\TextInput::make('label')
                ->label('Label')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('value_number')
                ->label('Nilai Angka')
                ->numeric()
                ->required()
                ->minValue(0)
                ->helperText('Untuk PPN, isi dengan angka persen. Contoh: 11 atau 12.')
                ->suffix(fn (Forms\Get $get) => $get('key') === ValuationSetting::KEY_PPN_PERCENT ? '%' : null),

            Forms\Components\TextInput::make('value_text')
                ->label('Nilai Teks')
                ->maxLength(255)
                ->placeholder('Opsional'),

            Forms\Components\Textarea::make('notes')
                ->label('Notes')
                ->rows(3)
                ->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->defaultSort('year', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('guidelineSet.name')
                    ->label('Guideline')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('year')
                    ->sortable(),
                Tables\Columns\TextColumn::make('key')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->searchable(),
                Tables\Columns\TextColumn::make('value_number')
                    ->label('Nilai')
                    ->formatStateUsing(function ($state, ValuationSetting $record) {
                        if ($record->key === ValuationSetting::KEY_PPN_PERCENT) {
                            return number_format((float) $state, 2, ',', '.') . '%';
                        }

                        return $state;
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('guideline_set_id')
                    ->label('Guideline Set')
                    ->options(fn () => GuidelineSet::query()->orderByDesc('year')->pluck('name', 'id')->toArray())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('year')
                    ->options(fn () => ValuationSetting::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->toArray()),
                Tables\Filters\SelectFilter::make('key')
                    ->options(ValuationSetting::keyOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListValuationSettings::route('/'),
            'create' => Pages\CreateValuationSetting::route('/create'),
            'edit' => Pages\EditValuationSetting::route('/{record}/edit'),
        ];
    }
}
