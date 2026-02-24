<?php

namespace App\Filament\Resources\ArticleCategoryResource\Pages;

use App\Filament\Resources\ArticleCategoryResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;

class ListArticleCategories extends ListRecords
{
    protected static string $resource = ArticleCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
