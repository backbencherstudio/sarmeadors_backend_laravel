<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait MergesSearchFilter
{
    /**
     * Promote a top-level `?search=` query param into the `filter[search]`
     * slot consumed by spatie/laravel-query-builder.
     */
    protected function mergeSearchFilter(Request $request): void
    {
        if ($request->filled('search') && ! $request->has('filter.search')) {
            $request->merge([
                'filter' => array_merge((array) $request->query('filter', []), [
                    'search' => $request->query('search'),
                ]),
            ]);
        }
    }

    protected function normalizeSearchValue(mixed $value): string
    {
        return collect(is_array($value) ? $value : [$value])
            ->map(fn (mixed $term): string => trim((string) $term))
            ->filter()
            ->implode(' ');
    }
}
