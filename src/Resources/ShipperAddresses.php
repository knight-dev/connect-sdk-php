<?php

declare(strict_types=1);

namespace Logicware\Connect\Resources;

class ShipperAddresses extends ResourceBase
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(string $shipperId): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/shippers/' . rawurlencode($shipperId) . '/addresses',
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @param array{warehouseId: string, freightType?: string, label?: string, isPrimary?: bool} $input
     * @return array<string, mixed>
     */
    public function create(string $shipperId, array $input): array
    {
        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/shippers/' . rawurlencode($shipperId) . '/addresses',
            'body' => $input,
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @param array{label?: string, isPrimary?: bool, isActive?: bool, deactivationReason?: string} $input
     * @return array<string, mixed>
     */
    public function update(string $shipperId, string $addressId, array $input): array
    {
        $raw = $this->http->request([
            'method' => 'PATCH',
            'path' => '/api/v1/shippers/' . rawurlencode($shipperId) . '/addresses/' . rawurlencode($addressId),
            'body' => $input,
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $shipperId, string $addressId, ?string $reason = null): array
    {
        $raw = $this->http->request([
            'method' => 'DELETE',
            'path' => '/api/v1/shippers/' . rawurlencode($shipperId) . '/addresses/' . rawurlencode($addressId),
            'query' => $reason !== null ? ['reason' => $reason] : [],
        ]);
        return $raw['data'] ?? [];
    }
}
