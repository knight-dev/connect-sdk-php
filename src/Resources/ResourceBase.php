<?php

declare(strict_types=1);

namespace Logicware\Connect\Resources;

use Generator;
use Logicware\Connect\Http\HttpClient;

/**
 * Base class for all SDK resources. Provides the HTTP client reference + small
 * helpers for envelope unwrapping and lazy pagination.
 */
abstract class ResourceBase
{
    public function __construct(protected readonly HttpClient $http)
    {
    }

    /**
     * Pull the `data` field out of the `{ success, data, ... }` envelope.
     * Returns the raw response when there is no data key (rare).
     *
     * @param array<string, mixed> $response
     * @return mixed
     */
    protected function unwrap(array $response): mixed
    {
        return $response['data'] ?? $response;
    }

    /**
     * Lazy pagination generator. Yields each item across all pages.
     *
     * @template T of array<string, mixed>
     * @param callable(int): array{data: list<T>, pagination?: array<string, int>} $fetcher
     * @return Generator<int, T, void, void>
     */
    protected function paginate(callable $fetcher, int $start = 1): Generator
    {
        $page = $start;
        while (true) {
            $result = $fetcher($page);
            foreach ($result['data'] as $item) {
                yield $item;
            }
            $pagination = $result['pagination'] ?? null;
            if ($pagination === null) {
                return;
            }
            $currentPage = $pagination['page'] ?? $page;
            $totalPages = $pagination['totalPages'] ?? 0;
            if ($currentPage >= $totalPages) {
                return;
            }
            $page++;
        }
    }

    /**
     * Build a page query object; clamps pageSize to 100 (matches server cap).
     *
     * @return array{page?: int, pageSize?: int}
     */
    protected function buildPageQuery(?int $page, ?int $pageSize): array
    {
        $out = [];
        if ($page !== null) {
            $out['page'] = $page;
        }
        if ($pageSize !== null) {
            $out['pageSize'] = min($pageSize, 100);
        }
        return $out;
    }
}
