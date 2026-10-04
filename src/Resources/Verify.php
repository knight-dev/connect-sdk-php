<?php

declare(strict_types=1);

namespace Logicware\Connect\Resources;

/**
 * Logicware Verify (beta) — declared-value verification with Jamaica customs estimates.
 *
 * Each successful check is one scan on your Verify plan. Receipt checks currently support
 * Amazon invoices only; other stores return `unsupported_merchant` (HTTP 422) and aren't counted.
 * Requires an API key with the `verify` scope.
 *
 * Reports include `verdict`, `riskScore`, `signals[]` (stable `code`s), per-item tariff
 * classification and packed weight estimates, and `customs` (estimated JMD duties, fees and GCT).
 */
class Verify extends ResourceBase
{
    /**
     * Check an item's value against live market prices; includes a customs estimate.
     *
     * @param array{itemDescription: string, merchantName?: string, declaredValueUsd?: float, quantity?: int} $input
     * @return array<string, mixed>
     */
    public function item(array $input): array
    {
        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/verify/item',
            'body' => $input,
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * Analyse a receipt (≤ 10 MB JPEG/PNG/WebP/PDF): extraction, tamper and arithmetic checks,
     * market pricing, tariff classification, weight estimate and customs estimate.
     *
     * @param string $fileContents Raw file bytes (e.g. file_get_contents($path)).
     * @param string $mimeType "image/jpeg", "image/png", "image/webp" or "application/pdf".
     * @return array<string, mixed>
     */
    public function receipt(string $fileContents, string $mimeType, ?float $declaredValueUsd = null): array
    {
        $body = [
            'fileBase64' => base64_encode($fileContents),
            'mimeType' => $mimeType,
        ];
        if ($declaredValueUsd !== null) {
            $body['declaredValueUsd'] = $declaredValueUsd;
        }

        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/verify/receipt',
            'body' => $body,
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * Fetch a previous verification.
     *
     * @return array<string, mixed>
     */
    public function get(string $verificationId): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/verify/' . rawurlencode($verificationId),
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * Scans used this calendar month, the allowance, and charges so far.
     *
     * @return array<string, mixed>
     */
    public function usage(): array
    {
        $raw = $this->http->request(['method' => 'GET', 'path' => '/api/v1/verify/usage']);
        return $raw['data'] ?? [];
    }
}
