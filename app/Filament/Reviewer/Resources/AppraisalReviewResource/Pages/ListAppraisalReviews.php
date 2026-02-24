<?php

namespace App\Filament\Reviewer\Resources\AppraisalReviewResource\Pages;

use App\Filament\Reviewer\Resources\AppraisalReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListAppraisalReviews extends ListRecords
{
    protected static string $resource = AppraisalReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
