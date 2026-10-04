<?php

declare(strict_types=1);

namespace Logicware\Connect\Webhooks;

/**
 * Verify an inbound external webhook from Logicware Connect.
 *
 * ```php
 * use Logicware\Connect\Webhooks\Verifier;
 *
 * $event = Verifier::verify(
 *     rawBody: $request->getBody(),
 *     signature: $request->getHeaderLine('X-Logicware-Signature'),
 *     timestamp: $request->getHeaderLine('X-Logicware-Timestamp'),
 *     secret: getenv('LW_WEBHOOK_SECRET'),
 * );
 *
 * match ($event['event']) {
 *     'package.received'       => handleReceived($event['data']),
 *     'package.status_changed' => handleStatusChanged($event['data']),
 *     // ...
 * };
 * ```
 *
 * Throws {@see WebhookVerificationException} on any problem.
 */
class Verifier
{
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * @return array{event: string, timestamp: string, companyId: string, companySlug: string, data: array<string, mixed>}
     */
    public static function verify(
        string $rawBody,
        ?string $signature,
        ?string $timestamp,
        string $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS,
    ): array {
        if ($signature === null || $signature === '') {
            throw new WebhookVerificationException(
                WebhookVerificationException::MISSING_SIGNATURE,
                'Missing X-Logicware-Signature header'
            );
        }
        if ($timestamp === null || $timestamp === '') {
            throw new WebhookVerificationException(
                WebhookVerificationException::MISSING_TIMESTAMP,
                'Missing X-Logicware-Timestamp header'
            );
        }

        if (!is_numeric($timestamp)) {
            throw new WebhookVerificationException(
                WebhookVerificationException::MISSING_TIMESTAMP,
                'X-Logicware-Timestamp is not a number'
            );
        }
        $ts = (int) $timestamp;
        $now = time();
        if (abs($now - $ts) > $toleranceSeconds) {
            throw new WebhookVerificationException(
                WebhookVerificationException::TIMESTAMP_OUT_OF_TOLERANCE,
                "Timestamp $ts is outside the {$toleranceSeconds}s tolerance window (now=$now)"
            );
        }

        $expectedHex = self::extractHexSignature($signature);
        $canonical = $timestamp . '.' . $rawBody;
        $actualHex = hash_hmac('sha256', $canonical, $secret);

        if (!hash_equals($expectedHex, $actualHex)) {
            throw new WebhookVerificationException(
                WebhookVerificationException::SIGNATURE_MISMATCH,
                'Signature does not match expected value'
            );
        }

        try {
            /** @var mixed $parsed */
            $parsed = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new WebhookVerificationException(
                WebhookVerificationException::INVALID_PAYLOAD,
                'Body is not valid JSON: ' . $e->getMessage()
            );
        }

        if (!is_array($parsed) || !isset($parsed['event']) || !isset($parsed['timestamp']) || !array_key_exists('data', $parsed)) {
            throw new WebhookVerificationException(
                WebhookVerificationException::INVALID_PAYLOAD,
                'Body is not a Logicware webhook envelope'
            );
        }

        /** @var array{event: string, timestamp: string, companyId: string, companySlug: string, data: array<string, mixed>} $parsed */
        return $parsed;
    }

    private static function extractHexSignature(string $raw): string
    {
        $trimmed = trim($raw);
        if (preg_match('/^sha256=([0-9a-f]+)$/i', $trimmed, $m) === 1) {
            return strtolower($m[1]);
        }
        if (preg_match('/^[0-9a-f]+$/i', $trimmed) === 1) {
            return strtolower($trimmed);
        }
        throw new WebhookVerificationException(
            WebhookVerificationException::INVALID_SIGNATURE_FORMAT,
            "Bad signature format: $raw"
        );
    }
}
