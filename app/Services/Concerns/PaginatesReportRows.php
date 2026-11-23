<?php

namespace App\Services\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * For a report whose tables are turned page by page from the address: a page past the last one gives the last, so a
 * hand-typed address never ends in an empty table.
 *
 * @phpstan-type ReportPage array{current_page: int, last_page: int, per_page: int, from: int|null, to: int|null, total: int}
 */
trait PaginatesReportRows
{
    private function clampedPage(Builder|QueryBuilder $query, int $page, int $perPage): LengthAwarePaginator
    {
        $paginator = $query->paginate($perPage, page: $page);

        return $page > $paginator->lastPage() ? $query->paginate($perPage, page: $paginator->lastPage()) : $paginator;
    }

    /**
     * @return ReportPage
     */
    private function pageOf(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }
}
