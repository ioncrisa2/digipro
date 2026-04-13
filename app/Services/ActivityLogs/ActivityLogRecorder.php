<?php

namespace App\Services\ActivityLogs;

use App\Models\ActivityLog;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ActivityLogRecorder
{
    private const MAX_DEPTH = 5;

    private const MAX_ITEMS = 50;

    private const MAX_STRING_LENGTH = 500;

    private const SENSITIVE_KEY_PARTS = [
        'password',
        'token',
        'secret',
        'authorization',
        'two_factor',
        'recovery_code',
        'remember',
        'backup_zip',
    ];

    public function record(Request $request, Response $response, ?User $actor = null): void
    {
        if (! $this->shouldRecord($request, $actor)) {
            return;
        }

        $route = $request->route();
        $routeName = $route?->getName();

        try {
            ActivityLog::query()->create([
                'user_id' => $actor?->id,
                'event_type' => $request->isMethod('GET') ? 'visit' : 'action',
                'workspace' => $this->resolveWorkspace($routeName, $request->path()),
                'action_label' => $this->resolveActionLabel($routeName, $request->method()),
                'route_name' => $routeName,
                'method' => $request->method(),
                'path' => '/' . ltrim($request->path(), '/'),
                'status_code' => $response->getStatusCode(),
                'route_params' => $this->sanitizeValue($route?->parametersWithoutNulls() ?? []),
                'query_payload' => $this->sanitizeValue($request->query()),
                'request_payload' => $this->buildRequestPayload($request),
                'response_meta' => $this->buildResponseMeta($request, $response),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, '...'),
                'occurred_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function shouldRecord(Request $request, ?User $actor): bool
    {
        if (! $actor || ! $request->route()) {
            return false;
        }

        if (in_array($request->method(), ['HEAD', 'OPTIONS'], true)) {
            return false;
        }

        if ($request->isMethod('GET')) {
            return $this->shouldRecordPageVisit($request);
        }

        return true;
    }

    private function shouldRecordPageVisit(Request $request): bool
    {
        $routeName = (string) $request->route()?->getName();

        if ($routeName === '') {
            return false;
        }

        if (! Str::startsWith($routeName, ['admin.', 'customer.', 'reviewer.', 'profile.'])) {
            return false;
        }

        if ($request->headers->has('X-Inertia-Partial-Data')) {
            return false;
        }

        if ($request->expectsJson() && ! $request->headers->has('X-Inertia')) {
            return false;
        }

        if (Str::endsWith($routeName, ['.location-options', '.id-preview', '.options'])) {
            return false;
        }

        return true;
    }

    private function buildRequestPayload(Request $request): ?array
    {
        if ($request->isMethod('GET')) {
            return null;
        }

        $payload = $this->sanitizeValue($request->all());

        if ($payload === []) {
            return null;
        }

        return is_array($payload) ? $payload : ['value' => $payload];
    }

    private function buildResponseMeta(Request $request, Response $response): ?array
    {
        $meta = array_filter([
            'redirect_to' => $response->headers->get('Location'),
            'is_inertia' => $request->headers->has('X-Inertia'),
        ], static fn (mixed $value): bool => ! is_null($value) && $value !== '');

        return $meta === [] ? null : $meta;
    }

    private function resolveWorkspace(?string $routeName, string $path): string
    {
        if (is_string($routeName) && $routeName !== '') {
            return match (Str::before($routeName, '.')) {
                'admin' => 'admin',
                'customer' => 'customer',
                'reviewer' => 'reviewer',
                'profile', 'notifications' => 'account',
                'login', 'register', 'password', 'verification', 'two-factor' => 'auth',
                default => 'public',
            };
        }

        if (Str::startsWith($path, 'admin')) {
            return 'admin';
        }

        if (Str::startsWith($path, 'reviewer')) {
            return 'reviewer';
        }

        return 'public';
    }

    private function resolveActionLabel(?string $routeName, string $method): string
    {
        $defaultVerb = match ($method) {
            'GET' => 'View',
            'POST' => 'Create or trigger',
            'PUT', 'PATCH' => 'Update',
            'DELETE' => 'Delete',
            default => Str::headline(Str::lower($method)),
        };

        if (! is_string($routeName) || $routeName === '') {
            return $defaultVerb . ' route';
        }

        $segments = collect(explode('.', $routeName))
            ->filter()
            ->values();

        $operation = (string) $segments->last();
        $resource = $segments
            ->slice(0, -1)
            ->reject(fn (string $segment): bool => in_array($segment, ['admin', 'customer', 'reviewer'], true))
            ->map(fn (string $segment): string => Str::headline(str_replace('-', ' ', $segment)))
            ->implode(' ');

        $verb = match ($operation) {
            'index' => 'Open',
            'show' => 'Inspect',
            'create' => 'Open create form for',
            'store' => 'Create',
            'edit' => 'Open edit form for',
            'update' => 'Update',
            'destroy' => 'Delete',
            'download' => 'Download',
            'restore' => 'Restore',
            'import' => 'Import',
            'export' => 'Export',
            'approve' => 'Approve',
            'reject' => 'Reject',
            'save' => 'Save',
            'done' => 'Mark done for',
            'archive' => 'Archive',
            'in-progress' => 'Mark in progress for',
            'read' => 'Read',
            'readAll' => 'Read all',
            default => $defaultVerb,
        };

        if ($resource === '') {
            return $verb;
        }

        return trim($verb . ' ' . $resource);
    }

    private function sanitizeValue(mixed $value, ?string $key = null, int $depth = 0): mixed
    {
        if ($depth >= self::MAX_DEPTH) {
            return '[max-depth]';
        }

        if ($this->isSensitiveKey($key)) {
            return '[redacted]';
        }

        if ($value instanceof UploadedFile) {
            return [
                'name' => $value->getClientOriginalName(),
                'mime' => $value->getClientMimeType(),
                'size' => $value->getSize(),
            ];
        }

        if ($value instanceof Model) {
            return $this->describeModel($value);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if (is_array($value)) {
            $sanitized = [];
            $count = 0;

            foreach ($value as $itemKey => $itemValue) {
                if ($count >= self::MAX_ITEMS) {
                    $sanitized['__truncated__'] = 'Additional items were omitted.';
                    break;
                }

                $sanitized[$itemKey] = $this->sanitizeValue(
                    $itemValue,
                    is_string($itemKey) ? $itemKey : $key,
                    $depth + 1,
                );
                $count++;
            }

            return $sanitized;
        }

        if (is_string($value)) {
            return Str::limit($value, self::MAX_STRING_LENGTH, '...');
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return Str::limit((string) $value, self::MAX_STRING_LENGTH, '...');
        }

        return '[' . strtolower(class_basename($value)) . ']';
    }

    private function isSensitiveKey(?string $key): bool
    {
        if (! is_string($key) || $key === '') {
            return false;
        }

        $normalized = Str::lower($key);

        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if (Str::contains($normalized, $part)) {
                return true;
            }
        }

        return $normalized === '_token';
    }

    private function describeModel(Model $model): array
    {
        $label = null;

        foreach (['request_number', 'name', 'title', 'subject', 'slug', 'email'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (filled($value)) {
                $label = Str::limit((string) $value, 120, '...');
                break;
            }
        }

        return array_filter([
            'type' => class_basename($model),
            'id' => $model->getKey(),
            'label' => $label,
        ], static fn (mixed $value): bool => ! is_null($value) && $value !== '');
    }
}
