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
 *
 * Pass `reference` (your package id): a repeat check of the same reference with the same input
 * returns the earlier result with `reused: true`, not counted as a scan, unless `force` is set.
 * Receipts are only flagged as "seen before" when used for a different reference. Every result
 * carries `usage` (scans used / remaining this month).
 */
class Verify extends ResourceBase
{
    /**
     * Check an item's value against live market prices; includes a customs estimate.
     *
     * @param array{itemDescription: string, merchantName?: string, declaredValueUsd?: float, quantity?: int, reference?: string, force?: bool, progressToken?: string} $input
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
     * @param array{reference?: string, force?: bool, progressToken?: string} $options
     * @return array<string, mixed>
     */
    public function receipt(string $fileContents, string $mimeType, ?float $declaredValueUsd = null, array $options = []): array
    {
        $body = [
            'fileBase64' => base64_encode($fileContents),
            'mimeType' => $mimeType,
        ];
        if ($declaredValueUsd !== null) {
            $body['declaredValueUsd'] = $declaredValueUsd;
        }
        foreach (['reference', 'force', 'progressToken'] as $key) {
            if (array_key_exists($key, $options) && $options[$key] !== null) {
                $body[$key] = $options[$key];
            }
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

    /**
     * Live progress of a check started with `progressToken`: `stage`, `message`, `done` and
     * `steps[]`. Useful when the check runs in a queued job and a UI polls your backend.
     * Null when the token is unknown (not started yet, or expired after 10 minutes).
     *
     * @return array<string, mixed>|null
     */
    public function progress(string $progressToken): ?array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/verify/progress/' . rawurlencode($progressToken),
        ]);
        return $raw['data'] ?? null;
    }

    /** A random token for `progressToken` / progress(). */
    public static function newProgressToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
