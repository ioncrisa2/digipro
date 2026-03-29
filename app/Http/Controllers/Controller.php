<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Base controller for application HTTP controllers.
 */
abstract class Controller
{
    protected function workspacePrefix(?Request $request = null): string
    {
        $request ??= request();

        return $request->routeIs('reviewer.*') ? 'reviewer' : 'admin';
    }

    protected function workspaceRouteName(string $suffix, ?Request $request = null): string
    {
        return $this->workspacePrefix($request) . '.' . ltrim($suffix, '.');
    }

    protected function workspaceRoute(string $suffix, mixed $parameters = [], ?Request $request = null): string
    {
        return route($this->workspaceRouteName($suffix, $request), $parameters);
    }

    protected function adminPerPage(?Request $request = null, int $default = 10, int $max = 100): int
    {
        $request ??= request();

        $value = (int) $request->query('per_page', $default);

        if ($value <= 0) {
            return $default;
        }

        return min($value, $max);
    }

    protected function paginatedRecordsPayload(object $records): array
    {
        return [
            'data' => $records->items(),
            'meta' => [
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
                'total' => $records->total(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'links' => $records->linkCollection()->toArray(),
            ],
        ];
    }
}
