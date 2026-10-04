<?php

declare(strict_types=1);

namespace Logicware\Connect\Resources;

/**
 * Jamaica customs from the 2026 Integrated Tariff (HS 2022). Deterministic and not metered.
 * Requires an API key with the `customs` or `verify` scope. Rates are fractions (0.2 = 20%).
 */
class Customs extends ResourceBase
{
    /**
     * Search tariff lines by item description ("bluetooth speaker", "sneakers").
     *
     * @return list<array<string, mixed>>
     */
    public function searchTariffs(string $query, int $limit = 15): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/customs/tariffs/search',
            'query' => ['q' => $query, 'limit' => $limit],
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * One tariff line with its rates.
     *
     * @return array<string, mixed>
     */
    public function getTariff(string $code): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/customs/tariffs/' . rawurlencode($code),
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * Estimate duties, fees and GCT in JMD, by tariff code or description.
     *
     * @param array{valueUsd: float, freightUsd?: float, insuranceUsd?: float, tariffCode?: string, description?: string, exchangeRate?: float} $input
     * @return array<string, mixed>
     */
    public function estimate(array $input): array
    {
        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/customs/estimate',
            'body' => $input,
        ]);
        return $raw['data'] ?? [];
    }
}
