<?php

namespace App\Services\Customer;

use App\Models\GuidelineSet;

class GuidelineSetResolver
{
    public function resolveId(): ?int
    {
        $activeId = GuidelineSet::query()
            ->where('is_active', true)
            ->value('id');

        if ($activeId) {
            return (int) $activeId;
        }

        $latestId = GuidelineSet::query()
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('id');

        return $latestId ? (int) $latestId : null;
    }
}
