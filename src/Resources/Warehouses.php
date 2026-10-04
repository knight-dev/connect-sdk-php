<?php

declare(strict_types=1);

namespace Logicware\Connect\Resources;

class Warehouses extends ResourceBase
{
    /**
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/warehouses',
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
            'path' => '/api/v1/warehouses/' . rawurlencode($id),
        ]);
        return $raw['data'] ?? [];
    }
}
