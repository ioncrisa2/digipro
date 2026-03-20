<?php

namespace App\Support;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ReviewerBtbCatalog
{
    public static function referenceSources(): array
    {
        return (array) config('reviewer_btb.reference_sources', []);
    }

    public static function worksheetColumns(): array
    {
        return (array) config('reviewer_btb.worksheet_columns', []);
    }

    public static function sections(): array
    {
        return (array) config('reviewer_btb.sections', []);
    }

    public static function lineItems(): array
    {
        return (array) config('reviewer_btb.line_items', []);
    }

    public static function templates(): array
    {
        return (array) config('reviewer_btb.templates', []);
    }

    public static function template(string $key): ?array
    {
        return Arr::get(self::templates(), $key);
    }

    public static function usageDefaults(?string $usage): array
    {
        if (! is_string($usage) || $usage === '') {
            return [];
        }

        return (array) config("reviewer_btb.usage_defaults.{$usage}", []);
    }

    public static function requiresBtb(?string $usage): bool
    {
        return (bool) Arr::get(self::usageDefaults($usage), 'uses_btb', false);
    }

    public static function candidateTemplatesForUsage(?string $usage): array
    {
        $candidates = array_values((array) Arr::get(self::usageDefaults($usage), 'candidate_templates', []));

        return $candidates !== [] ? $candidates : array_keys(self::templates());
    }

    public static function defaultTemplateForUsage(?string $usage): ?string
    {
        $default = Arr::get(self::usageDefaults($usage), 'default_template');

        if (is_string($default) && $default !== '') {
            return $default;
        }

        return self::candidateTemplatesForUsage($usage)[0] ?? null;
    }

    public static function resolveTemplateKey(?string $usage, ?string $buildingClass = null): ?string
    {
        if (! self::requiresBtb($usage)) {
            return null;
        }

        $normalizedClass = self::normalizeTemplateAlias($buildingClass);

        if ($normalizedClass !== null && Arr::has(self::templates(), $normalizedClass)) {
            return $normalizedClass;
        }

        return self::defaultTemplateForUsage($usage);
    }

    public static function templateForUsage(?string $usage, ?string $buildingClass = null): ?array
    {
        $key = self::resolveTemplateKey($usage, $buildingClass);

        return $key ? self::template($key) : null;
    }

    public static function normalizeTemplateAlias(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $normalized = Str::upper(trim(preg_replace('/\s+/', ' ', str_replace(['-', '_'], ' ', $value)) ?? $value));

        $aliases = (array) config('reviewer_btb.template_aliases', []);

        return $aliases[$normalized] ?? null;
    }
}
