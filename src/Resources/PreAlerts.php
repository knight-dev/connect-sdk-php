<?php

declare(strict_types=1);

namespace Logicware\Connect\Resources;

use Generator;

class PreAlerts extends ResourceBase
{
    /**
     * @param array{search?: string, status?: string, page?: int, pageSize?: int} $options
     * @return array{data: list<array<string, mixed>>, pagination: array<string, int>, stats: array<string, int>}
     */
    public function list(array $options = []): array
    {
        $query = $this->buildPageQuery($options['page'] ?? null, $options['pageSize'] ?? null);
        foreach (['search', 'status'] as $k) {
            if (isset($options[$k])) {
                $query[$k] = $options[$k];
            }
        }
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/prealerts',
            'query' => $query,
        ]);
        return [
            'data' => $raw['data'] ?? [],
            'pagination' => $raw['pagination'] ?? [],
            'stats' => $raw['stats'] ?? [],
        ];
    }

    /**
     * @param array{search?: string, status?: string, pageSize?: int} $options
     * @return Generator<int, array<string, mixed>, void, void>
     */
    public function listAll(array $options = []): Generator
    {
        return $this->paginate(fn (int $page) => $this->list(array_merge($options, ['page' => $page])));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/prealerts/' . rawurlencode($id),
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @return array{found: bool, data: array<string, mixed>|null}
     */
    public function lookupByTracking(string $trackingNumber): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/prealerts/lookup',
            'query' => ['tracking' => $trackingNumber],
        ]);
        return [
            'found' => (bool) ($raw['found'] ?? false),
            'data' => $raw['data'] ?? null,
        ];
    }

    /**
     * @param array{shipperAddressCode: string, carrierTrackingNumber?: string, carrier?: string, description?: string, expectedWeightLbs?: float, declaredValueUsd?: float, expectedPackageCount?: int, notes?: string, merchantName?: string, orderNumber?: string, itemCategory?: string, requiresSpecialHandling?: bool, specialHandlingInstructions?: string, expiresAt?: string} $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/prealerts',
            'body' => $input,
        ]);
        return $raw['data'] ?? [];
    }

    public function cancel(string $id): void
    {
        $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/prealerts/' . rawurlencode($id) . '/cancel',
        ]);
    }
}
