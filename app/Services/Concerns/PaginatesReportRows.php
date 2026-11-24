<?php

namespace App\Services\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

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
     * One page of rows already worked out in memory (a report that has to add its rows up before it can order or count them,
     * so the database cannot page them). A page past the last gives the last, and a page before the first the first.
     *
     * @template TRow
     *
     * @param  Collection<int, TRow>  $rows
     * @return array{data: list<TRow>, current_page: int, last_page: int, per_page: int, from: int|null, to: int|null, total: int}
     */
    private function paginateCollection(Collection $rows, int $page, int $perPage): array
    {
        $lastPage = max((int) ceil($rows->count() / $perPage), 1);
        $currentPage = min(max($page, 1), $lastPage);
        $pageRows = $rows->forPage($currentPage, $perPage)->values();

        return [
            'data' => $pageRows->all(),
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'from' => $pageRows->isEmpty() ? null : ($currentPage - 1) * $perPage + 1,
            'to' => $pageRows->isEmpty() ? null : ($currentPage - 1) * $perPage + $pageRows->count(),
            'total' => $rows->count(),
        ];
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
