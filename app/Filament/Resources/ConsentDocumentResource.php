<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConsentDocumentResource\Pages;
use App\Models\ConsentDocument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ConsentDocumentResource extends Resource
{
    protected static ?string $model = ConsentDocument::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationGroup = 'Konten & Legal';
    protected static ?int $navigationSort = 10;
    protected static ?string $navigationLabel = 'Persetujuan Layanan';
    protected static ?string $modelLabel = 'Persetujuan Layanan';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informasi Dokumen')
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Kode')
                        ->default('appraisal_request_consent')
                        ->required()
                        ->maxLength(100)
                        ->disabled(fn (?ConsentDocument $record) => $record?->status === 'published'),

                    Forms\Components\TextInput::make('version')
                        ->label('Versi')
                        ->required()
                        ->maxLength(50)
                        ->helperText('Contoh: 2026-02-04-v1.1')
                        ->disabled(fn (?ConsentDocument $record) => $record?->status === 'published'),

                    Forms\Components\TextInput::make('title')
                        ->label('Judul')
                        ->required()
                        ->maxLength(200)
                        ->disabled(fn (?ConsentDocument $record) => $record?->status === 'published'),

                    // Status: jangan izinkan Published lewat form. Published hanya lewat Action Publish.
                    Forms\Components\Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'archived' => 'Arsip',
                        ])
                        ->default('draft')
                        ->required()
                        ->disabledOn('create')
                        ->disabled(fn (?ConsentDocument $record) => $record?->status === 'published')
                        ->helperText('Published hanya bisa lewat tombol Publish (untuk menghitung hash & set published_at).'),

                    Forms\Components\TextInput::make('hash')
                        ->label('Hash')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText('Akan dihitung otomatis saat Publish.'),

                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Tanggal Publikasi')
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('checkbox_label')
                        ->label('Label Checkbox')
                        ->default('Saya telah membaca, memahami, dan menyetujui seluruh Persetujuan dan Disclaimer di atas.')
                        ->maxLength(255)
                        ->disabled(fn (?ConsentDocument $record) => $record?->status === 'published'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Isi Persetujuan')
                ->schema([
                    Forms\Components\Repeater::make('sections')
                        ->label('Sections')
                        ->minItems(1)
                        ->required()
                        ->disabled(fn (?ConsentDocument $record) => $record?->status === 'published')
                        ->schema([
                            Forms\Components\TextInput::make('heading')
                                ->required()
                                ->maxLength(200),

                            Forms\Components\Textarea::make('lead')
                                ->rows(2)
                                ->maxLength(500)
                                ->placeholder('Opsional, kalimat pembuka section'),

                            // Form items selalu pakai bentuk [{text: "..."}]
                            Forms\Components\Repeater::make('items')
                                ->label('Items')
                                ->minItems(1)
                                ->required()
                                ->schema([
                                    Forms\Components\Textarea::make('text')
                                        ->label('Item')
                                        ->required()
                                        ->rows(2)
                                        ->maxLength(1500),
                                ])
                                ->itemLabel(fn (array $state): string => str($state['text'] ?? 'Item')->limit(40)->toString()),
                        ])
                        ->itemLabel(fn (array $state): string => $state['heading'] ?? 'Section')

                        /**
                         * DB -> Form:
                         * - DB harusnya: items = [string, string]
                         * - Tapi data lama bisa campur: items = [{text:..}, "string"]
                         * Kita normalisasi agar form selalu: items = [{text:..}, {text:..}]
                         */
                        ->afterStateHydrated(function ($component, $state) {
                            if (! is_array($state)) return;

                            $mapped = collect($state)->map(function ($section) {
                                $items = collect($section['items'] ?? [])
                                    ->map(function ($i) {
                                        if (is_array($i)) {
                                            return ['text' => (string) ($i['text'] ?? '')];
                                        }
                                        return ['text' => (string) $i];
                                    })
                                    ->filter(fn ($i) => trim($i['text']) !== '')
                                    ->values()
                                    ->all();

                                return [
                                    'heading' => $section['heading'] ?? '',
                                    'lead' => $section['lead'] ?? null,
                                    'items' => $items,
                                ];
                            })->values()->all();

                            $component->state($mapped);
                        })

                        /**
                         * Form -> DB:
                         * Simpan selalu items = [string, string] agar Consent.vue tidak pernah render object JSON.
                         */
                        ->dehydrateStateUsing(function ($state) {
                            return collect($state ?? [])->map(function ($section) {
                                $items = collect($section['items'] ?? [])
                                    ->map(function ($i) {
                                        // bentuk normal form: ['text' => '...']
                                        if (is_array($i)) return $i['text'] ?? null;
                                        // fallback kalau sudah string
                                        return is_string($i) ? $i : null;
                                    })
                                    ->filter(fn ($v) => is_string($v) && trim($v) !== '')
                                    ->values()
                                    ->all();

                                return [
                                    'heading' => (string) ($section['heading'] ?? ''),
                                    'lead' => $section['lead'] ?? null,
                                    'items' => $items,
                                ];
                            })->values()->all();
                        }),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Judul')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('version')->label('Versi')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'primary' => 'draft',
                        'success' => 'published',
                        'gray' => 'archived',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('published_at')->label('Publikasi')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('hash')->label('Hash')->limit(12)->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->label('Diubah')->since()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ConsentDocument $record) => $record->status === 'draft')
                    ->action(function (ConsentDocument $record) {
                        // Auto-archive published doc sebelumnya untuk code yang sama
                        ConsentDocument::query()
                            ->forCode($record->code)
                            ->published()
                            ->where('id', '!=', $record->id)
                            ->update(['status' => 'archived']);

                        $record->status = 'published';
                        $record->published_at = now();
                        $record->hash = ConsentDocument::computeHash($record->payloadForHash());
                        $record->updated_by = Auth::id();
                        $record->save();
                    }),

                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate as Draft')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->visible(fn (ConsentDocument $record) => in_array($record->status, ['published', 'archived'], true))
                    ->action(function (ConsentDocument $record) {
                        $copy = $record->replicate();
                        $copy->status = 'draft';
                        $copy->published_at = null;
                        $copy->hash = str_repeat('0', 64);
                        $copy->version = $record->version . '-rev';
                        $copy->created_by = Auth::id();
                        $copy->updated_by = Auth::id();
                        $copy->save();
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn (ConsentDocument $record) => $record->status === 'draft'),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (ConsentDocument $record) => $record->status === 'draft'),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfoSection::make('Informasi Dokumen')
                ->columns(2)
                ->schema([
                    TextEntry::make('title')->label('Judul'),
                    TextEntry::make('code')->label('Kode'),
                    TextEntry::make('version')->label('Versi'),
                    TextEntry::make('status')->label('Status')->formatStateUsing(function ($state) {
                        return match ($state) {
                            'published' => 'Published',
                            'archived' => 'Arsip',
                            default => 'Draft',
                        };
                    }),
                    TextEntry::make('published_at')->label('Tanggal Publikasi')->dateTime('d M Y H:i')->placeholder('-'),
                    TextEntry::make('hash')->label('Hash')->placeholder('-'),
                    TextEntry::make('checkbox_label')->label('Label Checkbox')->columnSpanFull()->placeholder('-'),
                ]),

            InfoSection::make('Isi Persetujuan')
                ->schema([
                    TextEntry::make('sections')
                        ->label('Konten')
                        ->html()
                        ->formatStateUsing(function ($state) {
                            if (! is_array($state)) {
                                return new HtmlString('<p class="text-sm text-slate-500">Tidak ada konten.</p>');
                            }

                            $html = collect($state)->map(function ($section) {
                                $heading = e($section['heading'] ?? 'Section');
                                $lead = e($section['lead'] ?? '');
                                $items = collect($section['items'] ?? [])
                                    ->map(fn ($item) => '<li>' . e(is_string($item) ? $item : ($item['text'] ?? '')) . '</li>')
                                    ->filter()
                                    ->implode('');

                                $leadHtml = $lead !== '' ? "<p>{$lead}</p>" : '';
                                $listHtml = $items !== '' ? "<ul>{$items}</ul>" : '';

                                return "<h4>{$heading}</h4>{$leadHtml}{$listHtml}";
                            })->implode('');

                            return new HtmlString($html);
                        })
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getLabel(): ?string
    {
        return 'Persetujuan Layanan';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Persetujuan Layanan';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsentDocuments::route('/'),
            'create' => Pages\CreateConsentDocument::route('/create'),
            'view' => Pages\ViewConsentDocument::route('/{record}'),
            'edit' => Pages\EditConsentDocument::route('/{record}/edit'),
        ];
    }
}
