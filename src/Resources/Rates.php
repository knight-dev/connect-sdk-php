<?php

declare(strict_types=1);

namespace Logicware\Connect\Resources;

class Rates extends ResourceBase
{
    /**
     * @param array{warehouseId?: string, weightLbs: float, lengthIn?: float, widthIn?: float, heightIn?: float, declaredValueUsd?: float, freightType?: string, destinationParish?: string} $input
     * @return array<string, mixed>
     */
    public function calculate(array $input): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/rates/calculate',
            'query' => $input,
        ]);
        return $raw['data'] ?? [];
    }
}
