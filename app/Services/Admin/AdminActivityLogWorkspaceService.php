<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AdminActivityLogWorkspaceService
{
    public function indexPayload(array $filters, int $perPage): array
    {
        $records = ActivityLog::query()
            ->with('user:id,name,email')
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $innerQuery) use ($filters): void {
                    $innerQuery
                        ->where('action_label', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('route_name', 'like', '%' . $filters['q'] . '%')
                        ->orWhere('path', 'like', '%' . $filters['q'] . '%')
                        ->orWhereHas('user', function (Builder $userQuery) use ($filters): void {
                            $userQuery
                                ->where('name', 'like', '%' . $filters['q'] . '%')
                                ->orWhere('email', 'like', '%' . $filters['q'] . '%');
                        });
                });
            })
            ->when(
                $filters['workspace'] !== 'all',
                fn (Builder $query) => $query->where('workspace', $filters['workspace'])
            )
            ->when(
                $filters['method'] !== 'all',
                fn (Builder $query) => $query->where('method', $filters['method'])
            )
            ->when(
                $filters['event_type'] !== 'all',
                fn (Builder $query) => $query->where('event_type', $filters['event_type'])
            )
            ->when(
                $filters['status'] === 'success',
                fn (Builder $query) => $query->where('status_code', '<', 400)
            )
            ->when(
                $filters['status'] === 'error',
                fn (Builder $query) => $query->where('status_code', '>=', 400)
            )
            ->when(
                $filters['date_from'] !== '',
                fn (Builder $query) => $query->where('occurred_at', '>=', Carbon::createFromFormat('Y-m-d', $filters['date_from'])->startOfDay())
            )
            ->when(
                $filters['date_to'] !== '',
                fn (Builder $query) => $query->where('occurred_at', '<=', Carbon::createFromFormat('Y-m-d', $filters['date_to'])->endOfDay())
            )
            ->latest('occurred_at')
            ->paginate($perPage)
            ->withQueryString();

        $records->through(fn (ActivityLog $record): array => $this->row($record));

        $last24Hours = now()->subDay();
        $last7Days = now()->subDays(7);

        $topActors = ActivityLog::query()
            ->select('user_id', DB::raw('count(*) as total_events'))
            ->with('user:id,name,email')
            ->whereNotNull('user_id')
            ->where('occurred_at', '>=', $last24Hours)
            ->groupBy('user_id')
            ->orderByDesc('total_events')
            ->limit(5)
            ->get()
            ->map(fn (ActivityLog $record): array => [
                'user_id' => $record->user_id,
                'name' => $record->user?->name ?? 'Unknown user',
                'email' => $record->user?->email ?? '-',
                'total_events' => (int) ($record->total_events ?? 0),
            ])
            ->values();

        return [
            'filters' => $filters,
            'records' => $this->paginatedRecordsPayload($records),
            'summary' => [
                'events_24h' => ActivityLog::query()->where('occurred_at', '>=', $last24Hours)->count(),
                'unique_users_24h' => ActivityLog::query()
                    ->where('occurred_at', '>=', $last24Hours)
                    ->whereNotNull('user_id')
                    ->distinct('user_id')
                    ->count('user_id'),
                'actions_24h' => ActivityLog::query()
                    ->where('occurred_at', '>=', $last24Hours)
                    ->where('event_type', 'action')
                    ->count(),
                'failures_7d' => ActivityLog::query()
                    ->where('occurred_at', '>=', $last7Days)
                    ->where('status_code', '>=', 400)
                    ->count(),
                'latest_event_at' => ActivityLog::query()->max('occurred_at'),
            ],
            'workspaceOptions' => [
                ['value' => 'all', 'label' => 'Semua Workspace'],
                ['value' => 'admin', 'label' => 'Admin'],
                ['value' => 'customer', 'label' => 'Customer'],
                ['value' => 'reviewer', 'label' => 'Reviewer'],
                ['value' => 'account', 'label' => 'Account'],
                ['value' => 'auth', 'label' => 'Auth'],
                ['value' => 'public', 'label' => 'Public'],
            ],
            'methodOptions' => [
                ['value' => 'all', 'label' => 'Semua Method'],
                ['value' => 'GET', 'label' => 'GET'],
                ['value' => 'POST', 'label' => 'POST'],
                ['value' => 'PUT', 'label' => 'PUT'],
                ['value' => 'PATCH', 'label' => 'PATCH'],
                ['value' => 'DELETE', 'label' => 'DELETE'],
            ],
            'eventTypeOptions' => [
                ['value' => 'all', 'label' => 'Semua Tipe'],
                ['value' => 'visit', 'label' => 'Page Visit'],
                ['value' => 'action', 'label' => 'Action'],
            ],
            'statusOptions' => [
                ['value' => 'all', 'label' => 'Semua Status'],
                ['value' => 'success', 'label' => 'Success'],
                ['value' => 'error', 'label' => 'Error'],
            ],
            'topActors' => $topActors,
            'indexUrl' => route('admin.activity-logs.index'),
        ];
    }

    public function showPayload(ActivityLog $activityLog): array
    {
        $activityLog->loadMissing('user');

        return [
            'record' => [
                'id' => $activityLog->id,
                'action_label' => $activityLog->action_label,
                'event_type' => $activityLog->event_type,
                'event_type_label' => $this->eventTypeLabel($activityLog->event_type),
                'workspace' => $activityLog->workspace,
                'workspace_label' => $this->workspaceLabel($activityLog->workspace),
                'method' => $activityLog->method,
                'path' => $activityLog->path,
                'route_name' => $activityLog->route_name ?: '-',
                'status_code' => $activityLog->status_code,
                'status_tone' => $this->statusTone($activityLog->status_code),
                'ip_address' => $activityLog->ip_address ?: '-',
                'user_agent' => $activityLog->user_agent ?: '-',
                'occurred_at' => $activityLog->occurred_at?->toIso8601String(),
                'actor' => [
                    'id' => $activityLog->user?->id,
                    'name' => $activityLog->user?->name ?? 'Unknown user',
                    'email' => $activityLog->user?->email ?? '-',
                    'roles' => $activityLog->user?->getRoleNames()->values()->all() ?? [],
                ],
                'route_params' => $activityLog->route_params ?? [],
                'query_payload' => $activityLog->query_payload ?? [],
                'request_payload' => $activityLog->request_payload ?? [],
                'response_meta' => $activityLog->response_meta ?? [],
            ],
            'indexUrl' => route('admin.activity-logs.index'),
        ];
    }

    private function row(ActivityLog $record): array
    {
        return [
            'id' => $record->id,
            'action_label' => $record->action_label,
            'event_type' => $record->event_type,
            'event_type_label' => $this->eventTypeLabel($record->event_type),
            'workspace' => $record->workspace,
            'workspace_label' => $this->workspaceLabel($record->workspace),
            'method' => $record->method,
            'path' => $record->path,
            'route_name' => $record->route_name ?: '-',
            'status_code' => $record->status_code,
            'status_tone' => $this->statusTone($record->status_code),
            'actor_name' => $record->user?->name ?? 'Unknown user',
            'actor_email' => $record->user?->email ?? '-',
            'target_summary' => $this->targetSummary($record),
            'occurred_at' => $record->occurred_at?->toIso8601String(),
            'show_url' => route('admin.activity-logs.show', $record),
        ];
    }

    private function eventTypeLabel(?string $eventType): string
    {
        return match ($eventType) {
            'visit' => 'Page Visit',
            'action' => 'Action',
            default => '-',
        };
    }

    private function workspaceLabel(?string $workspace): string
    {
        return match ($workspace) {
            'admin' => 'Admin',
            'customer' => 'Customer',
            'reviewer' => 'Reviewer',
            'account' => 'Account',
            'auth' => 'Auth',
            'public' => 'Public',
            default => 'Unknown',
        };
    }

    private function statusTone(?int $statusCode): string
    {
        if ($statusCode === null) {
            return 'bg-slate-100 text-slate-800 border-slate-200';
        }

        if ($statusCode >= 400) {
            return 'bg-rose-100 text-rose-900 border-rose-200';
        }

        if ($statusCode >= 300) {
            return 'bg-amber-100 text-amber-900 border-amber-200';
        }

        return 'bg-emerald-100 text-emerald-900 border-emerald-200';
    }

    private function targetSummary(ActivityLog $record): string
    {
        $routeParams = collect($record->route_params ?? [])
            ->map(function (mixed $value, string|int $key): ?string {
                if (! is_array($value)) {
                    return is_scalar($value) ? ucfirst((string) $key) . ': ' . (string) $value : null;
                }

                $type = $value['type'] ?? ucfirst((string) $key);
                $label = $value['label'] ?? null;
                $id = $value['id'] ?? null;

                return trim($type . ($label ? ' ' . $label : '') . ($id ? ' #' . $id : ''));
            })
            ->filter()
            ->values();

        return $routeParams->first() ?? '-';
    }

    private function paginatedRecordsPayload(object $records): array
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
