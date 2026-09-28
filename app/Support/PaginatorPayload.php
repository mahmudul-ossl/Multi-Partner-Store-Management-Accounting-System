<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

final class PaginatorPayload
{
    /**
     * @param  class-string<JsonResource>  $resourceClass
     * @return array{
     *     data: list<mixed>,
     *     current_page: int,
     *     last_page: int,
     *     from: int|null,
     *     to: int|null,
     *     total: int,
     *     per_page: int
     * }
     */
    public static function make(LengthAwarePaginator $paginator, string $resourceClass): array
    {
        return [
            'data' => $resourceClass::collection($paginator->items())->resolve(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
        ];
    }
}
