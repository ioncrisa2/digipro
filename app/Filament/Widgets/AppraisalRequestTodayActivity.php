<?php

namespace App\Filament\Widgets;

use App\Models\AppraisalAsset;
use App\Models\AppraisalRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AppraisalRequestTodayActivity extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $today = now()->toDateString();

        $requestsToday = AppraisalRequest::query()
            ->whereDate('requested_at', $today)
            ->count();

        $assetsToday = AppraisalAsset::query()
            ->whereDate('created_at', $today)
            ->count();

        return [
            Stat::make('Permohonan Hari Ini', $requestsToday)
                ->description('Permohonan baru')
                ->color('success'),

            Stat::make('Aset Diunggah Hari Ini', $assetsToday)
                ->description('Total aset baru')
                ->color('primary'),
        ];
    }
}
