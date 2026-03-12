<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Get;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationGroup = 'Keuangan';
    protected static ?int $navigationSort = 10;

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Section::make('Informasi Pembayaran')
                    ->columns(2)
                    ->schema([
                        Select::make('appraisal_request_id')
                            ->label('Permohonan')
                            ->relationship('appraisalRequest', 'request_number')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('amount')
                            ->label('Jumlah (Rp)')
                            ->numeric()
                            ->required(),

                        Select::make('method')
                            ->label('Metode')
                            ->options([
                                'manual' => 'Transfer Manual',
                                'gateway' => 'Payment Gateway',
                            ])
                            ->default('manual')
                            ->required()
                            ->live(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'pending' => 'Menunggu',
                                'paid' => 'Dibayar',
                                'failed' => 'Gagal',
                                'expired' => 'Kedaluwarsa',
                                'rejected' => 'Ditolak',
                                'refunded' => 'Refund',
                            ])
                            ->default('pending')
                            ->required(),

                        TextInput::make('gateway')
                            ->label('Gateway')
                            ->visible(fn (Get $get) => $get('method') === 'gateway')
                            ->maxLength(60),

                        TextInput::make('external_payment_id')
                            ->label('Payment ID')
                            ->visible(fn (Get $get) => $get('method') === 'gateway')
                            ->maxLength(120),

                        DateTimePicker::make('paid_at')
                            ->label('Waktu Dibayar')
                            ->seconds(false),

                        FileUpload::make('proof_file_path')
                            ->label('Bukti Transfer')
                            ->disk('public')
                            ->directory('payment-proofs')
                            ->visibility('public')
                            ->openable()
                            ->downloadable()
                            ->visible(fn (Get $get) => $get('method') === 'manual')
                            ->afterStateUpdated(function ($state, Forms\Set $set, Get $get) {
                                if (! $state) return;
                                $set('proof_type', 'upload');
                            }),

                        TextInput::make('proof_original_name')
                            ->label('Nama File')
                            ->visible(fn (Get $get) => $get('method') === 'manual')
                            ->maxLength(255),

                        TextInput::make('proof_mime')
                            ->label('Mime')
                            ->visible(fn (Get $get) => $get('method') === 'manual')
                            ->maxLength(120),

                        TextInput::make('proof_size')
                            ->label('Ukuran (bytes)')
                            ->numeric()
                            ->visible(fn (Get $get) => $get('method') === 'manual'),

                        TextInput::make('proof_type')
                            ->label('Tipe Bukti')
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn (Get $get) => $get('method') === 'manual'),

                        Textarea::make('metadata')
                            ->label('Metadata (JSON)')
                            ->formatStateUsing(fn ($state) => self::formatJsonMetadata($state))
                            ->rules(['nullable', 'json'])
                            ->dehydrateStateUsing(fn ($state) => blank($state) ? null : json_decode((string) $state, true))
                            ->rows(4)
                            ->helperText('Isi JSON valid atau biarkan kosong.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('appraisalRequest.request_number')
                    ->label('Permohonan')
                    ->searchable(),

                Tables\Columns\TextColumn::make('method')
                    ->label('Metode')
                    ->formatStateUsing(fn ($state) => $state === 'gateway' ? 'Gateway' : 'Manual'),

                Tables\Columns\TextColumn::make('gateway')
                    ->label('Gateway')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('external_payment_id')
                    ->label('Payment ID')
                    ->limit(18)
                    ->placeholder('-'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => ['failed', 'rejected'],
                        'gray' => 'expired',
                        'secondary' => 'refunded',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'paid' => 'Dibayar',
                        'failed' => 'Gagal',
                        'expired' => 'Kedaluwarsa',
                        'rejected' => 'Ditolak',
                        'refunded' => 'Refund',
                        default => 'Menunggu',
                    }),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Jumlah')
                    ->money('idr'),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Dibayar')
                    ->dateTime('d M Y H:i')
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Menunggu',
                        'paid' => 'Dibayar',
                        'failed' => 'Gagal',
                        'expired' => 'Kedaluwarsa',
                        'rejected' => 'Ditolak',
                        'refunded' => 'Refund',
                    ])
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
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
            InfoSection::make('Informasi Pembayaran')
                ->columns(2)
                ->schema([
                    TextEntry::make('appraisalRequest.request_number')->label('Permohonan'),
                    TextEntry::make('amount')->label('Jumlah')->money('idr'),
                    TextEntry::make('method')->label('Metode'),
                    TextEntry::make('gateway')->label('Gateway')->placeholder('-'),
                    TextEntry::make('external_payment_id')->label('Payment ID')->placeholder('-'),
                    TextEntry::make('status')->label('Status')->placeholder('-'),
                    TextEntry::make('paid_at')->label('Dibayar')->dateTime('d M Y H:i')->placeholder('-'),
                ]),

            InfoSection::make('Bukti Pembayaran')
                ->schema([
                    ImageEntry::make('proof_file_path')
                        ->label('Preview Bukti (Gambar)')
                        ->disk('public')
                        ->visibility('public')
                        ->height(220)
                        ->visible(fn (Payment $record) => filled($record->proof_file_path) && str_starts_with((string) $record->proof_mime, 'image/')),
                    TextEntry::make('proof_original_name')
                        ->label('File Bukti')
                        ->state(fn (Payment $record) => $record->proof_original_name ?: '-')
                        ->url(fn (Payment $record) => filled($record->proof_file_path) && Storage::disk('public')->exists($record->proof_file_path)
                            ? Storage::disk('public')->url($record->proof_file_path)
                            : null)
                        ->openUrlInNewTab(),
                    TextEntry::make('proof_mime')->label('Mime')->placeholder('-'),
                    TextEntry::make('proof_size')->label('Ukuran (bytes)')->placeholder('-'),
                    TextEntry::make('proof_type')->label('Tipe')->placeholder('-'),
                    TextEntry::make('updated_at')->label('Diunggah Pada')->dateTime('d M Y H:i')->placeholder('-'),
                ]),

            InfoSection::make('Metadata')
                ->schema([
                    TextEntry::make('metadata')
                        ->label('Metadata')
                        ->state(fn (Payment $record) => self::metadataReadableLines($record->metadata))
                        ->bulleted()
                        ->listWithLineBreaks()
                        ->placeholder('-')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private static function metadataReadableLines(mixed $metadata): array
    {
        if (! is_array($metadata) || empty($metadata)) {
            return ['-'];
        }

        $lines = [];
        $flatten = function (array $data, string $prefix = '') use (&$flatten, &$lines): void {
            foreach ($data as $key => $value) {
                $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

                if (is_array($value)) {
                    $flatten($value, $path);
                    continue;
                }

                $label = ucwords(str_replace(['.', '_'], [' > ', ' '], $path));
                $text = match (true) {
                    $value === null => '-',
                    is_bool($value) => $value ? 'Ya' : 'Tidak',
                    default => (string) $value,
                };

                $lines[] = "{$label}: {$text}";
            }
        };

        $flatten($metadata);

        return empty($lines) ? ['-'] : $lines;
    }

    private static function formatJsonMetadata(mixed $state): ?string
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Pembayaran';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Pembayaran';
    }
}
