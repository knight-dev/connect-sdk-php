<?php

declare(strict_types=1);

namespace Logicware\Connect\Resources;

class MissingPackages extends ResourceBase
{
    /**
     * @param array{search?: string, status?: string, priority?: string, page?: int, pageSize?: int} $options
     * @return array<string, mixed>
     */
    public function list(array $options = []): array
    {
        $query = $this->buildPageQuery($options['page'] ?? null, $options['pageSize'] ?? null);
        foreach (['search', 'status', 'priority'] as $k) {
            if (isset($options[$k])) {
                $query[$k] = $options[$k];
            }
        }
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/missing-packages',
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
            'path' => '/api/v1/missing-packages/' . rawurlencode($id),
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @param array{shipperId: string, warehouseLocationId: string, trackingNumber: string, customerName?: string, carrier?: string, description?: string, merchantName?: string, orderNumber?: string, shippedDate?: string, expectedArrivalDate?: string, estimatedWeightLbs?: float, declaredValueUsd?: float, notes?: string, isUrgent?: bool} $input
     * @return array{id: string, status: string}
     */
    public function create(array $input): array
    {
        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/missing-packages',
            'body' => $input,
        ]);
        /** @var array{id: string, status: string} $data */
        $data = $raw['data'] ?? ['id' => '', 'status' => ''];
        return $data;
    }

    public function cancel(string $id, ?string $reason = null): void
    {
        $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/missing-packages/' . rawurlencode($id) . '/cancel',
            'body' => $reason !== null ? ['reason' => $reason] : new \stdClass(),
        ]);
    }

    public function close(string $id, ?string $resolutionNotes = null): void
    {
        $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/missing-packages/' . rawurlencode($id) . '/close',
            'body' => $resolutionNotes !== null ? ['resolutionNotes' => $resolutionNotes] : new \stdClass(),
        ]);
    }
}
