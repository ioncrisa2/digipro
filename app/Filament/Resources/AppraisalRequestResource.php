<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use App\Enums\PurposeEnum;
use Filament\Tables\Table;
use App\Enums\ReportTypeEnum;
use App\Models\AppraisalRequest;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\RawJs;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use App\Enums\ContractStatusEnum;
use App\Enums\AppraisalStatusEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section as InfoSection;
use App\Filament\Resources\AppraisalRequestResource\Pages;
use App\Filament\Resources\AppraisalRequestResource\RelationManagers\AssetsRelationManager;
use App\Filament\Resources\AppraisalRequestResource\RelationManagers\AssetDocumentsRelationManager;
use App\Filament\Resources\AppraisalRequestResource\RelationManagers\AssetPhotosRelationManager;
use App\Filament\Resources\AppraisalRequestResource\RelationManagers\OfferNegotiationsRelationManager;

class AppraisalRequestResource extends Resource
{
    protected static ?string $model = AppraisalRequest::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationGroup = 'Penilaian Properti';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Permohonan')
                    ->columns(2)
                    ->schema([
                        TextInput::make('request_number')
                            ->label('Nomer Permohonan')
                            ->disabled()
                            ->hint('Diisi otomatis')
                            ->maxLength(50),

                        Select::make('purpose')
                            ->label('Tujuan Penilaian')
                            ->options(PurposeEnum::options())
                            ->required(),

                        Select::make('status')
                            ->label('Status')
                            ->options(AppraisalStatusEnum::options())
                            ->default(AppraisalStatusEnum::Draft)
                            ->required()
                            ->native(false),

                        DateTimePicker::make('requested_at')
                            ->label('Tanggal Permohonan')
                            ->seconds(false)
                            ->required(),

                        DateTimePicker::make('verified_at')
                            ->label('Tanggal Verifikasi')
                            ->seconds(false)

                    ]),

                Section::make('Pemberi Tugas')
                    ->columns(2)
                    ->schema([
                        TextInput::make('requester_name')
                            ->label('Pemohon (User)')
                            ->default(fn (?AppraisalRequest $record) => $record?->user?->name)
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('client_name')
                            ->label('Pemberi Tugas / Bank')
                            ->maxLength(255)
                            ->helperText('Kosongkan jika sama dengan pemohon.'),
                    ]),

                Section::make('Kontrak & Penawaran')
                    ->columns(3)
                    ->schema([

                        TextInput::make('contract_sequence')
                            ->label('No. Penawaran')
                            ->numeric()
                            ->minValue(1)
                            ->live()
                            ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::syncContractNumber($set, $get))
                            ->afterStateHydrated(fn ($state, Set $set, Get $get) => self::syncContractNumber($set, $get, true)),

                        Hidden::make('contract_office_code')
                            ->default('0'),

                        Hidden::make('contract_month'),

                        Hidden::make('contract_year'),

                        DatePicker::make('contract_date')
                            ->label('Tanggal Kontrak'),

                        TextInput::make('contract_number')
                            ->label('Nomor Penawaran')
                            ->disabled()
                            ->dehydrated()
                            ->dehydrateStateUsing(fn ($state, Get $get) => self::buildContractNumber($get('contract_sequence')))
                            ->columnSpan(3)
                            ->helperText('Format: {nomor}/AGR/DP/{bulan}/{tahun} (bulan & tahun dari tanggal hari ini)'),

                        Select::make('contract_status')
                            ->label('Status Kontrak')
                            ->options(ContractStatusEnum::options())
                            ->default(ContractStatusEnum::None)
                            ->native(false),

                        Select::make('report_type')
                            ->label('Jenis Laporan')
                            ->options(ReportTypeEnum::options())
                            ->default(ReportTypeEnum::Terinci)
                            ->native(false),

                        TextInput::make('valuation_duration_days')
                            ->label('Jangka Waktu Pelaksanaan (hari kerja)')
                            ->numeric()
                            ->minValue(1),

                        TextInput::make('offer_validity_days')
                            ->label('Masa Berlaku Penawaran (hari kerja)')
                            ->numeric()
                            ->minValue(1)
                    ]),

                Section::make('Fee Penilaian')
                    ->columns(3)
                    ->schema([
                        TextInput::make('fee_total')
                            ->label('Total Fee (Rp)')
                            ->numeric()
                            ->mask(RawJs::make('$money($input, \',\', \'.\')'))
                            ->stripCharacters('.')
                            ->prefix('Rp'),

                        Toggle::make('fee_has_dp')
                            ->label('Menggunakan DP?')
                            ->inline(false)
                            ->live(), // Add this to enable reactivity

                        TextInput::make('fee_dp_percent')
                            ->label('Persentase DP (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->default(50)
                            ->helperText('Contoh: Penjaminan 50% di muka')
                            ->visible(fn(Forms\Get $get) => $get('fee_has_dp') == true),
                    ]),

                Section::make('Catatan & Permintaan Khusus')
                    ->schema([
                        Textarea::make('user_request_note')
                            ->label('Permintaan / Catatan dari User')
                            ->rows(3),

                        Textarea::make('notes')
                            ->label('Catatan Internal (Admin / Reviewer)')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'offerNegotiations as negotiation_rounds_used' => fn ($q) => $q->where('action', 'counter_request'),
            ]))
            ->defaultSort('requested_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('request_number')
                    ->label('No. Permohonan')
                    ->searchable(),

                Tables\Columns\TextColumn::make('client_name')
                    ->label('Pemberi Tugas')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\BadgeColumn::make('purpose')
                    ->label('Tujuan')
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') return '-';
                        if ($state instanceof PurposeEnum) return $state->label();
                        return PurposeEnum::from($state)->label();
                    })
                    ->colors([
                        'success'   => PurposeEnum::JualBeli->value,
                        'warning'   => PurposeEnum::PenjaminanUtang->value,
                        'danger'    => PurposeEnum::Lelang->value
                    ]),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') return '-';
                        if ($state instanceof AppraisalStatusEnum) return $state->label();
                        return AppraisalStatusEnum::from($state)->label();
                    })
                    ->colors([
                        'secondary' =>  AppraisalStatusEnum::Draft->value,
                        'info'      =>  AppraisalStatusEnum::Submitted->value,
                        'warning'   =>  [
                            AppraisalStatusEnum::DocsIncomplete->value,
                            AppraisalStatusEnum::WaitingOffer->value,
                            AppraisalStatusEnum::OfferSent->value,
                            AppraisalStatusEnum::WaitingSignature->value
                        ],
                        'success'   =>  [
                            AppraisalStatusEnum::Verified->value,
                            AppraisalStatusEnum::ContractSigned->value,
                            AppraisalStatusEnum::ValuationCompleted->value,
                            AppraisalStatusEnum::ReportReady->value,
                            AppraisalStatusEnum::Completed->value
                        ],
                        'danger'    =>  AppraisalStatusEnum::Cancelled->value,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('assets_count')
                    ->label('Aset')
                    ->counts('assets'),

                Tables\Columns\TextColumn::make('negotiation_rounds_used')
                    ->label('Nego')
                    ->badge()
                    ->formatStateUsing(fn ($state) => (int) $state)
                    ->color(fn ($state) => ((int) $state) > 0 ? 'warning' : 'gray')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('contract_status')
                    ->label('Status Kontrak')
                    ->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') return '-';
                        if ($state instanceof ContractStatusEnum) return $state->label();
                        return ContractStatusEnum::from($state)->label();
                    })
                    ->colors([
                        'secondary' => [
                            ContractStatusEnum::None->value,
                            ContractStatusEnum::Draft->value,
                        ],
                        'info' => [
                            ContractStatusEnum::SentToClient->value,
                            ContractStatusEnum::WaitingSignature->value,
                        ],
                        'warning' => [
                            ContractStatusEnum::Negotiation->value,
                        ],
                        'success' => [
                            ContractStatusEnum::ContractSigned->value,
                        ],
                        'danger' => [
                            ContractStatusEnum::Cancelled->value,
                        ],
                    ]),

                Tables\Columns\TextColumn::make('fee_total')
                    ->label('Fee (Rp)')
                    ->money('idr')
                    ->sortable(),

                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Tgl Permohonan')
                    ->dateTime('d-m-Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(AppraisalStatusEnum::options())
                    ->native(false),

                Tables\Filters\SelectFilter::make('contract_status')
                    ->label('Status Kontrak')
                    ->options(ContractStatusEnum::options())
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('verifyDocs')
                    ->label('Verifikasi Dokumen')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn (AppraisalRequest $record) => in_array($record->status?->value ?? $record->status, [
                        AppraisalStatusEnum::Submitted->value,
                        AppraisalStatusEnum::DocsIncomplete->value,
                    ], true))
                    ->action(function (AppraisalRequest $record) {
                        $record->update([
                            'status' => AppraisalStatusEnum::WaitingOffer,
                            'verified_at' => now(),
                        ]);
                    }),

                Tables\Actions\Action::make('docsIncomplete')
                    ->label('Dokumen Kurang')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AppraisalRequest $record) => in_array($record->status?->value ?? $record->status, [
                        AppraisalStatusEnum::Submitted->value,
                        AppraisalStatusEnum::WaitingOffer->value,
                        AppraisalStatusEnum::OfferSent->value,
                    ], true))
                    ->action(fn (AppraisalRequest $record) => $record->update([
                        'status' => AppraisalStatusEnum::DocsIncomplete,
                    ])),

                Tables\Actions\Action::make('sendOffer')
                    ->label(fn (AppraisalRequest $record) => ($record->status?->value ?? $record->status) === AppraisalStatusEnum::WaitingOffer->value
                        ? 'Kirim Counter Offer'
                        : 'Kirim Penawaran')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn (AppraisalRequest $record) => in_array($record->status?->value ?? $record->status, [
                        AppraisalStatusEnum::Verified->value,
                        AppraisalStatusEnum::WaitingOffer->value,
                        AppraisalStatusEnum::OfferSent->value,
                    ], true))
                    ->action(function (AppraisalRequest $record) {
                        $contractNumber = $record->contract_number ?: self::buildContractNumber($record->contract_sequence);
                        $statusBefore = $record->status?->value ?? $record->status;
                        $contractStatusBefore = $record->contract_status?->value ?? $record->contract_status;
                        $actionType = $contractStatusBefore === ContractStatusEnum::Negotiation->value ? 'offer_revised' : 'offer_sent';
                        $round = (int) $record->offerNegotiations()->where('action', 'counter_request')->count();

                        if (empty($record->fee_total) || empty($contractNumber)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Data penawaran belum lengkap')
                                ->body('Isi total fee dan nomor penawaran terlebih dahulu sebelum mengirim penawaran.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $record->update([
                            'contract_number' => $contractNumber,
                            'contract_office_code' => $record->contract_office_code ?: '0',
                            'contract_month' => $record->contract_month ?: now()->month,
                            'contract_year' => $record->contract_year ?: now()->year,
                            'contract_date' => optional($record->contract_date)->toDateString() ?: now()->toDateString(),
                            'status' => AppraisalStatusEnum::OfferSent,
                            'contract_status' => ContractStatusEnum::SentToClient,
                        ]);

                        $record->offerNegotiations()->create([
                            'user_id' => auth()->id(),
                            'action' => $actionType,
                            'round' => $round > 0 ? $round : null,
                            'offered_fee' => $record->fee_total,
                            'meta' => [
                                'status_before' => $statusBefore,
                                'contract_status_before' => $contractStatusBefore,
                                'status_after' => AppraisalStatusEnum::OfferSent->value,
                                'contract_status_after' => ContractStatusEnum::SentToClient->value,
                            ],
                        ]);
                    }),

                Tables\Actions\Action::make('markPaid')
                    ->label('Verifikasi Pembayaran')
                    ->icon('heroicon-o-banknotes')
                    ->requiresConfirmation()
                    ->visible(fn (AppraisalRequest $record) => ($record->status?->value ?? $record->status) === AppraisalStatusEnum::ContractSigned->value)
                    ->disabled(fn (AppraisalRequest $record) => ! self::canVerifyPayment($record))
                    ->tooltip(fn (AppraisalRequest $record) => self::canVerifyPayment($record)
                        ? null
                        : 'Aksi aktif setelah pembayaran Midtrans berstatus Dibayar.')
                    ->action(function (AppraisalRequest $record) {
                        if (! self::canVerifyPayment($record)) {
                            \Filament\Notifications\Notification::make()
                                ->title('Pembayaran belum siap diverifikasi')
                                ->body('Pastikan pembayaran Midtrans sudah berstatus Dibayar.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $record->update([
                            'status' => AppraisalStatusEnum::ValuationOnProgress,
                        ]);
                    }),

                Tables\Actions\Action::make('contractSigned')
                    ->label('Kontrak Ditandatangani')
                    ->icon('heroicon-o-pencil-square')
                    ->requiresConfirmation()
                    ->visible(fn (AppraisalRequest $record) => ($record->status?->value ?? $record->status) === AppraisalStatusEnum::WaitingSignature->value)
                    ->action(fn (AppraisalRequest $record) => $record->update([
                        'status' => AppraisalStatusEnum::ContractSigned,
                        'contract_status' => ContractStatusEnum::ContractSigned,
                    ])),
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
            InfoSection::make('Informasi Permohonan')
                ->columns(3)
                ->schema([
                    TextEntry::make('request_number')->label('Nomor'),
                    TextEntry::make('purpose')->label('Tujuan')->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') return '-';
                        if ($state instanceof PurposeEnum) return $state->label();
                        return PurposeEnum::from($state)->label();
                    }),
                    TextEntry::make('status')->label('Status')->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') return '-';
                        if ($state instanceof AppraisalStatusEnum) return $state->label();
                        return AppraisalStatusEnum::from($state)->label();
                    }),
                    TextEntry::make('requested_at')->label('Tanggal Permohonan')->dateTime('d M Y H:i'),
                    TextEntry::make('verified_at')->label('Tanggal Verifikasi')->dateTime('d M Y H:i')->placeholder('-'),
                    TextEntry::make('report_type')->label('Jenis Laporan')->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') return '-';
                        if ($state instanceof ReportTypeEnum) return $state->label();
                        return ReportTypeEnum::from($state)->label();
                    }),
                ]),

            InfoSection::make('Pemberi Tugas')
                ->columns(2)
                ->schema([
                    TextEntry::make('client_name')->label('Nama')->placeholder('-'),
                    TextEntry::make('contract_number')->label('Nomer Kontrak')->placeholder('-'),
                ]),

            InfoSection::make('Penawaran & Fee')
                ->columns(3)
                ->schema([
                    TextEntry::make('fee_total')->label('Total Fee')->money('idr')->placeholder('-'),
                    TextEntry::make('fee_has_dp')->label('DP?')->formatStateUsing(fn ($state) => $state ? 'Ya' : 'Tidak'),
                    TextEntry::make('fee_dp_percent')->label('DP (%)')->suffix('%')->placeholder('-'),
                    TextEntry::make('offer_validity_days')->label('Masa Berlaku Penawaran')->suffix(' hari')->placeholder('-'),
                ]),

            InfoSection::make('Kontrak')
                ->columns(3)
                ->schema([
                    TextEntry::make('contract_number')->label('Nomor Kontrak')->placeholder('-'),
                    TextEntry::make('contract_date')->label('Tanggal Kontrak')->date('d M Y')->placeholder('-'),
                    TextEntry::make('contract_status')->label('Status Kontrak')->formatStateUsing(function ($state) {
                        if ($state === null || $state === '') return '-';
                        if ($state instanceof ContractStatusEnum) return $state->label();
                        return ContractStatusEnum::from($state)->label();
                    })->placeholder('-'),
                    TextEntry::make('valuation_duration_days')->label('Durasi Penilaian')->suffix(' hari')->placeholder('-'),
                ]),

            InfoSection::make('Negosiasi Fee')
                ->columns(3)
                ->schema([
                    TextEntry::make('negotiation_rounds_used')
                        ->label('Putaran Terpakai')
                        ->state(fn (AppraisalRequest $record) => (int) $record->offerNegotiations()->where('action', 'counter_request')->count())
                        ->badge(),
                    TextEntry::make('negotiation_rounds_remaining')
                        ->label('Sisa Putaran')
                        ->state(fn (AppraisalRequest $record) => max(0, 3 - (int) $record->offerNegotiations()->where('action', 'counter_request')->count()))
                        ->badge(),
                    TextEntry::make('latest_expected_fee')
                        ->label('Harapan Fee Terakhir')
                        ->state(fn (AppraisalRequest $record) => $record->offerNegotiations()->where('action', 'counter_request')->latest('id')->value('expected_fee'))
                        ->money('idr')
                        ->placeholder('-'),
                    TextEntry::make('latest_negotiation_reason')
                        ->label('Alasan Keberatan Terakhir')
                        ->state(fn (AppraisalRequest $record) => $record->offerNegotiations()->where('action', 'counter_request')->latest('id')->value('reason') ?: '-')
                        ->columnSpanFull(),
                ]),

            InfoSection::make('Catatan')
                ->columns(1)
                ->schema([
                    TextEntry::make('user_request_note')->label('Catatan User')->placeholder('-'),
                    TextEntry::make('notes')->label('Catatan Internal')->placeholder('-'),
                ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            AssetsRelationManager::class,
            AssetDocumentsRelationManager::class,
            AssetPhotosRelationManager::class,
            OfferNegotiationsRelationManager::class,
        ];
    }

    private static function syncContractNumber(Set $set, Get $get, bool $force = false): void
    {
        $sequence = $get('contract_sequence');
        $current = $get('contract_number');
        $currentDate = $get('contract_date');

        if (! $force && self::isFormattedContractNumber($current)) {
            return;
        }

        $set('contract_office_code', '0');
        $set('contract_month', now()->month);
        $set('contract_year', now()->year);
        if (! $currentDate) {
            $set('contract_date', now()->toDateString());
        }

        $set('contract_number', self::buildContractNumber($sequence));
    }

    private static function buildContractNumber($sequence): ?string
    {
        $raw = preg_replace('/\D+/', '', (string) $sequence);
        if ($raw === '') {
            return null;
        }

        $date = now();
        $month = str_pad((string) $date->month, 2, '0', STR_PAD_LEFT);
        $year = (string) $date->year;

        $padded = str_pad($raw, 5, '0', STR_PAD_LEFT);

        return "{$padded}/AGR/DP/{$month}/{$year}";
    }

    private static function isFormattedContractNumber(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return (bool) preg_match(
            "/^\\d{5,}\\/AGR\\/DP\\/\\d{2}\\/\\d{4}$/",
            $value
        );
    }

    private static function canVerifyPayment(AppraisalRequest $record): bool
    {
        $latestPayment = $record->payments()
            ->latest('id')
            ->first(['method', 'status']);

        if (! $latestPayment) {
            return false;
        }

        return $latestPayment->method === 'gateway'
            && $latestPayment->status === 'paid';
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAppraisalRequests::route('/'),
            'create' => Pages\CreateAppraisalRequest::route('/create'),
            'edit' => Pages\EditAppraisalRequest::route('/{record}/edit'),
            'view' => Pages\ViewAppraisalRequest::route('/{record}'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Permohonan Penilaian';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Permohonan Penilaian';
    }
}
