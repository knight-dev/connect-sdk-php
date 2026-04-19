<?php

declare(strict_types=1);

namespace Logicware\Connect\Resources;

class Manifests extends ResourceBase
{
    /**
     * @param array{status?: string, type?: string, page?: int, pageSize?: int} $options
     * @return array<string, mixed>
     */
    public function list(array $options = []): array
    {
        $query = $this->buildPageQuery($options['page'] ?? null, $options['pageSize'] ?? null);
        foreach (['status', 'type'] as $k) {
            if (isset($options[$k])) {
                $query[$k] = $options[$k];
            }
        }
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/manifests',
            'query' => $query,
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/manifests/' . rawurlencode($id),
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @param array{type: string, carrierName?: string, originCode?: string, originName?: string, destinationCode?: string, destinationName?: string, estimatedDeparture?: string, estimatedArrival?: string, notes?: string, isOpen?: bool, autoLinkPackages?: bool, shippingCycleLabel?: string} $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/manifests',
            'body' => $input,
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(string $id, array $input): array
    {
        $raw = $this->http->request([
            'method' => 'PUT',
            'path' => '/api/v1/manifests/' . rawurlencode($id),
            'body' => $input,
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function setOpen(string $id, bool $isOpen, bool $autoLinkPackages = false): array
    {
        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/manifests/' . rawurlencode($id) . '/open',
            'body' => ['isOpen' => $isOpen, 'autoLinkPackages' => $autoLinkPackages],
        ]);
        return $raw['data'] ?? [];
    }

    /** @return array<string, mixed> */
    public function close(string $id): array
    {
        return $this->setOpen($id, false);
    }

    /** @return array<string, mixed> */
    public function reopen(string $id, bool $autoLinkPackages = false): array
    {
        return $this->setOpen($id, true, $autoLinkPackages);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function finalize(string $id, array $input = []): array
    {
        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/manifests/' . rawurlencode($id) . '/finalize',
            'body' => $input,
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @param array{status: string, actualDeparture?: string, actualArrival?: string, customsEntryNumber?: string, customsDeclarationNumber?: string, customsExchangeRate?: float, freightChargesUsd?: float, totalDutiesPaid?: float, dutiesCurrency?: string, notes?: string} $input
     * @return array<string, mixed>
     */
    public function setStatus(string $id, array $input): array
    {
        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/manifests/' . rawurlencode($id) . '/status',
            'body' => $input,
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @param list<string> $packageIds
     * @return array{addedCount: int, skippedCount: int, errors: list<string>}
     */
    public function addPackages(string $id, array $packageIds): array
    {
        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/manifests/' . rawurlencode($id) . '/packages',
            'body' => ['packageIds' => $packageIds],
        ]);
        /** @var array{addedCount: int, skippedCount: int, errors: list<string>} $data */
        $data = $raw['data'] ?? ['addedCount' => 0, 'skippedCount' => 0, 'errors' => []];
        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function removePackage(string $id, string $packageId): array
    {
        $raw = $this->http->request([
            'method' => 'DELETE',
            'path' => '/api/v1/manifests/' . rawurlencode($id) . '/packages/' . rawurlencode($packageId),
        ]);
        return $raw['data'] ?? [];
    }

    public function delete(string $id): void
    {
        $this->http->request([
            'method' => 'DELETE',
            'path' => '/api/v1/manifests/' . rawurlencode($id),
        ]);
    }
}
