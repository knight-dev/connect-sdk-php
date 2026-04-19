<?php

declare(strict_types=1);

namespace Logicware\Connect\Resources;

use Generator;
use InvalidArgumentException;

class Intake extends ResourceBase
{
    /**
     * Search unidentified intake packages. At least one of `tracking` or
     * `customerName` is required — browse-all is intentionally blocked.
     *
     * @return list<array<string, mixed>>
     */
    public function searchUnidentified(?string $tracking = null, ?string $customerName = null): array
    {
        if ($tracking === null && $customerName === null) {
            throw new InvalidArgumentException(
                'searchUnidentified: at least one of tracking or customerName is required'
            );
        }
        $query = [];
        if ($tracking !== null) {
            $query['tracking'] = $tracking;
        }
        if ($customerName !== null) {
            $query['customerName'] = $customerName;
        }
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/intake/unidentified/search',
            'query' => $query,
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @param array{page?: int, pageSize?: int} $options
     * @return array{data: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function listUnclaimed(array $options = []): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/intake/unclaimed',
            'query' => $this->buildPageQuery($options['page'] ?? null, $options['pageSize'] ?? null),
        ]);
        return ['data' => $raw['data'] ?? [], 'pagination' => $raw['pagination'] ?? []];
    }

    /**
     * @param array{pageSize?: int} $options
     * @return Generator<int, array<string, mixed>, void, void>
     */
    public function listAllUnclaimed(array $options = []): Generator
    {
        return $this->paginate(fn (int $page) => $this->listUnclaimed(array_merge($options, ['page' => $page])));
    }

    /**
     * @param array{page?: int, pageSize?: int} $options
     * @return array{data: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function listReceived(string $sinceIso, array $options = []): array
    {
        $query = $this->buildPageQuery($options['page'] ?? null, $options['pageSize'] ?? null);
        $query['since'] = $sinceIso;
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/intake/received',
            'query' => $query,
        ]);
        return ['data' => $raw['data'] ?? [], 'pagination' => $raw['pagination'] ?? []];
    }

    /**
     * @param array{pageSize?: int} $options
     * @return Generator<int, array<string, mixed>, void, void>
     */
    public function listAllReceived(string $sinceIso, array $options = []): Generator
    {
        return $this->paginate(fn (int $page) => $this->listReceived($sinceIso, array_merge($options, ['page' => $page])));
    }
}
