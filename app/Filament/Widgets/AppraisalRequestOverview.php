<?php

namespace App\Filament\Widgets;

use App\Enums\AppraisalStatusEnum;
use App\Models\AppraisalRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AppraisalRequestOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Submitted', AppraisalRequest::query()
                ->where('status', AppraisalStatusEnum::Submitted)
                ->count())
                ->description('Menunggu verifikasi')
                ->color('info'),

            Stat::make('Docs Incomplete', AppraisalRequest::query()
                ->where('status', AppraisalStatusEnum::DocsIncomplete)
                ->count())
                ->description('Perlu perbaikan')
                ->color('warning'),

            Stat::make('Waiting Offer', AppraisalRequest::query()
                ->where('status', AppraisalStatusEnum::WaitingOffer)
                ->count())
                ->description('Siap diberi penawaran')
                ->color('warning'),

            Stat::make('Offer Sent', AppraisalRequest::query()
                ->where('status', AppraisalStatusEnum::OfferSent)
                ->count())
                ->description('Menunggu pembayaran')
                ->color('primary'),

            Stat::make('Waiting Signature', AppraisalRequest::query()
                ->where('status', AppraisalStatusEnum::WaitingSignature)
                ->count())
                ->description('Menunggu tanda tangan')
                ->color('warning'),

            Stat::make('Contract Signed', AppraisalRequest::query()
                ->where('status', AppraisalStatusEnum::ContractSigned)
                ->count())
                ->description('Siap proses valuasi')
                ->color('success'),
        ];
    }
}
