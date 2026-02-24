<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\ArticleCategory;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BlogOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected function getStats(): array
    {
        $totalViews = Article::query()->sum('views');
        $totalArticles = Article::query()->count();
        $topCategory = ArticleCategory::query()
            ->withSum('articles as views_sum', 'views')
            ->orderByRaw('COALESCE(views_sum, 0) DESC')
            ->first();

        $name = $topCategory?->name ?? '-';
        $views = number_format((int) ($topCategory->views_sum ?? 0));

        return [
            Stat::make('Total Views', number_format($totalViews))
                ->description('Akumulasi semua artikel')
                ->color('primary'),
            Stat::make('Total Artikel', number_format($totalArticles))
                ->description('Semua artikel di blog')
                ->color('success'),
            Stat::make('Top Category',$name)
                ->description("{$views} views")
                ->color('info')
        ];
    }
}
