<?php

declare(strict_types=1);

namespace Logicware\Connect\Resources;

use Generator;

class Packages extends ResourceBase
{
    /**
     * @param array{shipperId?: string, status?: string, search?: string, page?: int, pageSize?: int} $options
     * @return array{data: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function list(array $options = []): array
    {
        $query = $this->buildPageQuery($options['page'] ?? null, $options['pageSize'] ?? null);
        foreach (['shipperId', 'status', 'search'] as $k) {
            if (isset($options[$k])) {
                $query[$k] = $options[$k];
            }
        }
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/packages',
            'query' => $query,
        ]);
        return ['data' => $raw['data'] ?? [], 'pagination' => $raw['pagination'] ?? []];
    }

    /**
     * @param array{shipperId?: string, status?: string, search?: string, pageSize?: int} $options
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
            'path' => '/api/v1/packages/' . rawurlencode($id),
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getByTracking(string $trackingNumber): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/packages/track/' . rawurlencode($trackingNumber),
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * Create a pre-alert package. Server returns the new package's id +
     * internalBarcode wrapped in {data: {...}}.
     *
     * Accepted keys (all optional unless noted):
     *   shipperId            string  UUID of the owning shipper
     *   shipperAddressCode   string  Address code (alternative to shipperId)
     *   trackingNumber       string  Carrier tracking number
     *   description          string
     *   merchantName         string  Merchant / store name
     *   sourceMarketplace    string  Marketplace (Amazon, Shein, etc.)
     *   declaredValueUsd     float
     *   weightLbs            float
     *   packageType          string  Box | Bag | Envelope | Tube | Crate | Pallet | Irregular | Other
     *   freightType          string  Air | Sea (defaults to Air)
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/packages',
            'body' => $input,
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * Update a package. All keys are optional — only supplied fields are touched.
     *
     * Accepted keys:
     *   description          string
     *   merchantName         string
     *   declaredValueUsd     float
     *   weightLbs            float
     *   lengthIn             float
     *   widthIn              float
     *   heightIn             float
     *   sourceMarketplace    string
     *   packageType          string  Box | Bag | Envelope | Tube | Crate | Pallet | Irregular | Other
     *   freightType          string  Air | Sea
     *   condition            string  Good | MinorDamage | ModerateDamage | SevereDamage | Tampered | WetDamaged | Fragile
     *   conditionNotes       string
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(string $id, array $input): array
    {
        $raw = $this->http->request([
            'method' => 'PUT',
            'path' => '/api/v1/packages/' . rawurlencode($id),
            'body' => $input,
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @param array{status?: string, search?: string, page?: int, pageSize?: int} $options
     * @return array{data: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function forShipper(string $shipperId, array $options = []): array
    {
        return $this->list(array_merge($options, ['shipperId' => $shipperId]));
    }

    /**
     * @param array{page?: int, pageSize?: int} $options
     * @return array{data: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function forManifest(string $manifestId, array $options = []): array
    {
        $query = $this->buildPageQuery($options['page'] ?? null, $options['pageSize'] ?? null);
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/manifests/' . rawurlencode($manifestId) . '/packages',
            'query' => $query,
        ]);
        return ['data' => $raw['data'] ?? [], 'pagination' => $raw['pagination'] ?? []];
    }
}
