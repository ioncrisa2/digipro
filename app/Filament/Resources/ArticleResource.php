<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use FilamentTiptapEditor\TiptapEditor;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\Layout\Grid;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationGroup = 'Konten';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('Artikel')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Judul')
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(200)
                            ->unique(ignoreRecord: true)
                            ->helperText('Akan digunakan di URL artikel.'),

                        FileUpload::make('cover_image_path')
                            ->label('Cover')
                            ->disk('public')
                            ->directory('articles')
                            ->visibility('public')
                            ->image()
                            ->imagePreviewHeight('160')
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),

                        Textarea::make('excerpt')
                            ->label('Ringkasan')
                            ->rows(3)
                            ->columnSpanFull(),

                        Select::make('category_id')
                            ->label('Kategori')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Nama')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, $get) {
                                        $slug = $get('slug');
                                        if (! $slug) {
                                            $set('slug', Str::slug((string) $state));
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(200),
                                Textarea::make('description')
                                    ->label('Deskripsi')
                                    ->rows(2),
                                TextInput::make('sort_order')
                                    ->label('Urutan')
                                    ->numeric()
                                    ->default(0),
                                Toggle::make('is_active')
                                    ->label('Aktif')
                                    ->inline(false)
                                    ->default(true),
                            ])
                            ->createOptionUsing(function (array $data) {
                                return \App\Models\ArticleCategory::create($data)->id;
                            })
                            ->columnSpanFull(),

                        Select::make('tags')
                            ->label('Tag')
                            ->multiple()
                            ->relationship('tags', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Nama')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set, $get) {
                                        $slug = $get('slug');
                                        if (! $slug) {
                                            $set('slug', Str::slug((string) $state));
                                        }
                                    }),
                                TextInput::make('slug')
                                    ->label('Slug')
                                    ->required()
                                    ->maxLength(200),
                                Toggle::make('is_active')
                                    ->label('Aktif')
                                    ->inline(false)
                                    ->default(true),
                            ])
                            ->createOptionUsing(function (array $data) {
                                return \App\Models\Tag::create($data)->id;
                            })
                            ->columnSpanFull(),

                        TiptapEditor::make('content_html')
                            ->label('Konten')
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('SEO')
                    ->columns(2)
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta Title')
                            ->maxLength(150)
                            ->helperText('Opsional. Jika kosong, gunakan judul artikel.'),

                        Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->rows(2)
                            ->maxLength(255)
                            ->live()
                            ->helperText(function (?string $state) {
                                $count = mb_strlen($state ?? '');
                                $color = $count >= 160 ? 'text-emerald-600' : 'text-slate-400';

                                return new HtmlString(
                                    '<div class="text-xs text-slate-500">Ringkasan singkat untuk SEO (maks 255 karakter).</div>' .
                                    '<div class="text-xs ' . $color . '">' . $count . '/160 karakter</div>'
                                );
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Publikasi')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_published')
                            ->label('Tampilkan Artikel')
                            ->inline(false)
                            ->default(false)
                            ->live(),

                        DateTimePicker::make('published_at')
                            ->label('Tanggal Publikasi')
                            ->seconds(false)
                            ->visible(fn (Forms\Get $get) => (bool) $get('is_published')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->contentGrid([
                'sm' => 2,
                'lg' => 3,
            ])
            ->columns([
                Stack::make([
                    Tables\Columns\ImageColumn::make('cover_image_path')
                        ->label('Cover')
                        ->disk('public')
                        ->height(160)
                        ->width('100%')
                        ->extraImgAttributes(['class' => 'object-cover rounded-lg w-full h-full'])
                        ->placeholder('No cover'),

                    Tables\Columns\TextColumn::make('title')
                        ->label('Judul')
                        ->searchable()
                        ->size('lg')
                        ->weight('bold')
                        ->wrap(),

                    Tables\Columns\TextColumn::make('excerpt')
                        ->label('Ringkasan')
                        ->limit(150)
                        ->wrap(),

                    Split::make([
                        Tables\Columns\TextColumn::make('category.name')
                            ->label('Kategori')
                            ->badge()
                            ->placeholder('-'),
                        Tables\Columns\TextColumn::make('tags.name')
                            ->label('Tag')
                            ->badge()
                            ->separator(', ')
                            ->placeholder('-'),
                    ]),

                    Split::make([
                        Tables\Columns\IconColumn::make('is_published')
                            ->label('Published')
                            ->boolean()
                            ->trueIcon('heroicon-m-eye')
                            ->falseIcon('heroicon-m-archive-box'),
                        Tables\Columns\TextColumn::make('views')
                            ->label('Views')
                            ->formatStateUsing(fn ($state) => number_format((int) ($state ?? 0))),
                        Tables\Columns\TextColumn::make('published_at')
                            ->label('Tanggal')
                            ->dateTime('d M Y')
                            ->placeholder('-'),
                        Tables\Columns\TextColumn::make('updated_at')
                            ->label('Diubah')
                            ->since()
                            ->tooltip(fn ($state) => $state),
                    ]),
                ])
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Published'),
            ])
            ->actions([
                Tables\Actions\Action::make('togglePublish')
                    ->label(fn (Article $record) => $record->is_published ? 'Archive' : 'Publish')
                    ->icon(fn (Article $record) => $record->is_published ? 'heroicon-o-archive-box' : 'heroicon-o-paper-airplane')
                    ->color(fn (Article $record) => $record->is_published ? 'gray' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Article $record) {
                        if ($record->is_published) {
                            $record->is_published = false;
                            $record->published_at = null;
                        } else {
                            $record->is_published = true;
                            $record->published_at = $record->published_at ?? now();
                        }

                        $record->save();
                    }),
                Tables\Actions\Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Article $record) => route('articles.show', $record->slug) . '?preview=1')
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfoSection::make('Artikel')
                ->schema([
                    TextEntry::make('title')->label('Judul'),
                    TextEntry::make('slug')->label('Slug'),
                    TextEntry::make('category.name')->label('Kategori')->placeholder('-'),
                    TextEntry::make('tags.name')
                        ->label('Tag')
                        ->formatStateUsing(function ($state) {
                            if (empty($state)) {
                                return '-';
                            }
                            if (is_array($state)) {
                                return implode(', ', $state);
                            }
                            return (string) $state;
                        }),
                    TextEntry::make('excerpt')->label('Ringkasan')->columnSpanFull()->placeholder('-'),
                    TextEntry::make('content_html')->label('Konten')->html()->columnSpanFull(),
                ]),

            InfoSection::make('SEO')
                ->columns(2)
                ->schema([
                    TextEntry::make('meta_title')->label('Meta Title')->placeholder('-'),
                    TextEntry::make('meta_description')->label('Meta Description')->placeholder('-'),
                ]),

            InfoSection::make('Publikasi')
                ->columns(2)
                ->schema([
                    TextEntry::make('is_published')->label('Published')->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak'),
                    TextEntry::make('published_at')->label('Tanggal Publikasi')->dateTime('d M Y H:i')->placeholder('-'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
            'view' => Pages\ViewArticle::route('/{record}'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\BlogOverview::class
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Artikel';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Artikel';
    }
}
