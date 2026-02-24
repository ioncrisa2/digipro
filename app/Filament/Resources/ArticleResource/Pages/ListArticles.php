<?php

namespace App\Filament\Resources\ArticleResource\Pages;

use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\ArticleResource;
use Filament\Actions\CreateAction;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return ArticleResource::getWidgets();
    }
}
