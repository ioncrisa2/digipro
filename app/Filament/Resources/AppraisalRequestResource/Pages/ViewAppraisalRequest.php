<?php

namespace App\Filament\Resources\AppraisalRequestResource\Pages;

use App\Enums\AppraisalStatusEnum;
use App\Enums\ContractStatusEnum;
use App\Filament\Resources\AppraisalRequestResource;
use App\Models\AppraisalRequest;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\RawJs;

class ViewAppraisalRequest extends ViewRecord
{
    protected static string $resource = AppraisalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verifyDocs')
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

                    Notification::make()
                        ->title('Dokumen diverifikasi')
                        ->body('Status berpindah ke Menunggu Penawaran.')
                        ->success()
                        ->send();
                }),

            Action::make('approveLatestNegotiation')
                ->label('Setujui Nego User')
                ->icon('heroicon-o-hand-thumb-up')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Setujui Harapan Fee User?')
                ->modalDescription('Fee akan mengikuti harapan fee terakhir dari user, lalu penawaran revisi dikirim.')
                ->visible(fn (AppraisalRequest $record) => ($record->status?->value ?? $record->status) === AppraisalStatusEnum::WaitingOffer->value
                    && $record->offerNegotiations()->where('action', 'counter_request')->exists())
                ->action(function (AppraisalRequest $record) {
                    $latestCounter = $record->offerNegotiations()
                        ->where('action', 'counter_request')
                        ->latest('id')
                        ->first();

                    if (! $latestCounter) {
                        Notification::make()
                            ->title('Data negosiasi tidak ditemukan')
                            ->body('Belum ada keberatan fee dari user untuk disetujui.')
                            ->warning()
                            ->send();
                        return;
                    }

                    if ($latestCounter->expected_fee === null) {
                        Notification::make()
                            ->title('Harapan fee user belum diisi')
                            ->body('User hanya mengirim alasan negosiasi tanpa nominal. Gunakan "Kirim Counter Offer" untuk merespons.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $contractNumber = $record->contract_number ?: self::buildContractNumber($record->contract_sequence);
                    if (empty($contractNumber)) {
                        Notification::make()
                            ->title('Nomor penawaran belum tersedia')
                            ->body('Isi No. Penawaran terlebih dahulu sebelum menyetujui negosiasi user.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $approvedFee = (int) $latestCounter->expected_fee;
                    $contractDate = optional($record->contract_date)->toDateString() ?: now()->toDateString();
                    $statusBefore = $record->status?->value ?? $record->status;
                    $contractStatusBefore = $record->contract_status?->value ?? $record->contract_status;
                    $round = (int) $record->offerNegotiations()->where('action', 'counter_request')->count();

                    $record->update([
                        'fee_total' => $approvedFee,
                        'contract_office_code' => $record->contract_office_code ?: '0',
                        'contract_month' => $record->contract_month ?: now()->month,
                        'contract_year' => $record->contract_year ?: now()->year,
                        'contract_date' => $contractDate,
                        'contract_number' => $contractNumber,
                        'status' => AppraisalStatusEnum::OfferSent,
                        'contract_status' => ContractStatusEnum::SentToClient,
                    ]);

                    $record->offerNegotiations()->create([
                        'user_id' => auth()->id(),
                        'action' => 'offer_revised',
                        'round' => $round > 0 ? $round : null,
                        'offered_fee' => $approvedFee,
                        'expected_fee' => $approvedFee,
                        'meta' => [
                            'flow' => 'approve_latest_counter_request',
                            'counter_request_id' => $latestCounter->id,
                            'status_before' => $statusBefore,
                            'contract_status_before' => $contractStatusBefore,
                            'status_after' => AppraisalStatusEnum::OfferSent->value,
                            'contract_status_after' => ContractStatusEnum::SentToClient->value,
                        ],
                    ]);

                    Notification::make()
                        ->title('Keberatan user disetujui')
                        ->body('Penawaran revisi berhasil dikirim mengikuti harapan fee user.')
                        ->success()
                        ->send();
                }),

            Action::make('inputOffer')
                ->label(fn (AppraisalRequest $record) => ($record->status?->value ?? $record->status) === AppraisalStatusEnum::WaitingOffer->value
                    ? 'Kirim Counter Offer'
                    : 'Input & Kirim Penawaran')
                ->icon('heroicon-o-document-text')
                ->modalHeading(fn (AppraisalRequest $record) => ($record->status?->value ?? $record->status) === AppraisalStatusEnum::WaitingOffer->value
                    ? 'Kirim Counter Offer'
                    : 'Input & Kirim Penawaran')
                ->visible(fn (AppraisalRequest $record) => in_array($record->status?->value ?? $record->status, [
                    AppraisalStatusEnum::Verified->value,
                    AppraisalStatusEnum::WaitingOffer->value,
                    AppraisalStatusEnum::OfferSent->value,
                ], true))
                ->form([
                    TextInput::make('fee_total')
                        ->label('Total Fee (Rp)')
                        ->numeric()
                        ->mask(RawJs::make('$money($input, \',\', \'.\')'))
                        ->stripCharacters('.')
                        ->prefix('Rp')
                        ->required(),

                    Toggle::make('fee_has_dp')
                        ->label('Menggunakan DP?')
                        ->inline(false)
                        ->live(),

                    TextInput::make('fee_dp_percent')
                        ->label('Persentase DP (%)')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->step(0.01)
                        ->default(50)
                        ->visible(fn (Get $get) => $get('fee_has_dp') == true),

                    TextInput::make('contract_sequence')
                        ->label('No. Penawaran')
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn ($state, Set $set, Get $get) => self::syncContractNumber($set, $get))
                        ->afterStateHydrated(fn ($state, Set $set, Get $get) => self::syncContractNumber($set, $get, true)),

                    Hidden::make('contract_number'),

                    TextInput::make('contract_number_display')
                        ->label('Nomor Penawaran')
                        ->disabled()
                        ->dehydrated(false)
                        ->default(fn (Get $get) => self::buildContractNumber($get('contract_sequence')))
                        ->formatStateUsing(fn ($state, Get $get) => self::buildContractNumber($get('contract_sequence'))),

                    TextInput::make('offer_validity_days')
                        ->label('Masa Berlaku Penawaran (hari kerja)')
                        ->numeric()
                        ->minValue(1),
                ])
                ->fillForm(fn (AppraisalRequest $record) => [
                    'fee_total' => self::resolveDefaultOfferFee($record),
                    'fee_has_dp' => $record->fee_has_dp,
                    'fee_dp_percent' => $record->fee_dp_percent,
                    'contract_sequence' => $record->contract_sequence,
                    'contract_number' => $record->contract_number,
                    'offer_validity_days' => $record->offer_validity_days,
                ])
                ->action(function (array $data, AppraisalRequest $record) {
                    $contractNumber = self::buildContractNumber($data['contract_sequence'] ?? null);
                    $contractDate = optional($record->contract_date)->toDateString() ?: now()->toDateString();
                    $statusBefore = $record->status?->value ?? $record->status;
                    $contractStatusBefore = $record->contract_status?->value ?? $record->contract_status;
                    $actionType = $contractStatusBefore === ContractStatusEnum::Negotiation->value ? 'offer_revised' : 'offer_sent';
                    $round = (int) $record->offerNegotiations()->where('action', 'counter_request')->count();

                    $record->update([
                        'fee_total' => $data['fee_total'],
                        'fee_has_dp' => $data['fee_has_dp'] ?? false,
                        'fee_dp_percent' => $data['fee_dp_percent'] ?? null,
                        'contract_sequence' => $data['contract_sequence'] ?? null,
                        'contract_office_code' => '0',
                        'contract_month' => now()->month,
                        'contract_year' => now()->year,
                        'contract_date' => $contractDate,
                        'contract_number' => $contractNumber,
                        'offer_validity_days' => $data['offer_validity_days'] ?? null,
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

                    Notification::make()
                        ->title('Penawaran berhasil dikirim')
                        ->success()
                        ->send();
                }),
        ];
    }

    private static function syncContractNumber(Set $set, Get $get, bool $force = false): void
    {
        $sequence = $get('contract_sequence');
        $current = $get('contract_number');

        if (! $force && self::isFormattedContractNumber($current)) {
            return;
        }

        $formatted = self::buildContractNumber($sequence);
        $set('contract_number', $formatted);
        $set('contract_number_display', $formatted);
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

    private static function resolveDefaultOfferFee(AppraisalRequest $record): ?int
    {
        $status = $record->status?->value ?? $record->status;
        if ($status === AppraisalStatusEnum::WaitingOffer->value) {
            $latestExpectedFee = $record->offerNegotiations()
                ->where('action', 'counter_request')
                ->latest('id')
                ->value('expected_fee');

            if ($latestExpectedFee !== null) {
                return (int) $latestExpectedFee;
            }
        }

        return $record->fee_total !== null ? (int) $record->fee_total : null;
    }
}
