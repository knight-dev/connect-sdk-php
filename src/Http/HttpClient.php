<?php

declare(strict_types=1);

namespace Logicware\Connect\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Request;
use Logicware\Connect\Client;
use Logicware\Connect\Exceptions\LogicwareApiException;
use Logicware\Connect\Exceptions\LogicwareNetworkException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Thin PSR-18 HTTP wrapper. Handles auth, JSON encoding/decoding, 429/5xx
 * retry with exponential backoff, and uniform error shaping. Never throws
 * raw PSR exceptions — callers see LogicwareApiException or
 * LogicwareNetworkException only.
 *
 * @phpstan-type RequestOptions array{
 *   method: 'GET'|'POST'|'PUT'|'PATCH'|'DELETE',
 *   path: string,
 *   query?: array<string, scalar|null>,
 *   body?: mixed,
 *   headers?: array<string, string>,
 *   idempotencyKey?: string,
 *   requestId?: string,
 * }
 */
class HttpClient
{
    private readonly ClientInterface $client;
    private readonly string $userAgent;
    /** @var (callable(array<string, mixed>): void)|null */
    private $recorder;

    /**
     * @param (callable(array<string, mixed>): void)|null $recorder
     *        Optional observer. Called once per finished request with a
     *        structured entry describing the method, URL, headers (with
     *        X-Api-Key masked), bodies, status, duration and any error.
     *        Intended for the demo playground — production integrations
     *        leave it null and pay no overhead.
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        ?ClientInterface $httpClient,
        int $timeoutMs,
        private readonly int $maxAttempts,
        ?string $userAgentSuffix,
        ?callable $recorder = null,
    ) {
        $this->client = $httpClient ?? new GuzzleClient([
            'timeout' => $timeoutMs / 1000,
            'http_errors' => false,
        ]);
        $this->userAgent = Client::SDK_NAME . '/' . Client::SDK_VERSION
            . ($userAgentSuffix !== null ? ' ' . $userAgentSuffix : '');
        $this->recorder = $recorder;
    }

    /**
     * @param RequestOptions $options
     * @return array<string, mixed>|null Parsed JSON body, or null for 204.
     */
    public function request(array $options): ?array
    {
        $startedAt = microtime(true);
        $attempt = 0;
        $lastRequestMeta = null;
        while (true) {
            $attempt++;
            try {
                [$response, $requestMeta] = $this->attempt($options);
                $lastRequestMeta = $requestMeta;
            } catch (ClientExceptionInterface $e) {
                if ($attempt < $this->maxAttempts && $this->isNetworkRetryable($e)) {
                    $this->sleep(self::backoffMs($attempt));
                    continue;
                }
                $this->record($options, $lastRequestMeta, null, $startedAt, $e->getMessage());
                throw new LogicwareNetworkException('Network error: ' . $e->getMessage(), $e);
            }

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                $parsed = $this->parseJson($response);
                $this->record($options, $lastRequestMeta, $response, $startedAt, null);
                return $parsed;
            }

            if ($attempt < $this->maxAttempts && $this->isStatusRetryable($status)) {
                $this->sleep($this->retryAfterMs($response) ?? self::backoffMs($attempt));
                continue;
            }

            $this->record($options, $lastRequestMeta, $response, $startedAt, null);
            throw $this->buildApiException($response);
        }
    }

    /**
     * Raw pass-through used by the demo playground's /api/proxy endpoint so
     * it can capture the exact status + body + headers without the request()
     * method's exception mapping. Always goes through attempt() (which records
     * via the recorder hook) so edit-and-resend shows up in the drawer.
     *
     * @param RequestOptions $options
     * @return array{status: int, headers: array<string, string>, body: string, request: array<string, mixed>, durationMs: float, error: ?string}
     */
    public function rawRequest(array $options): array
    {
        $startedAt = microtime(true);
        try {
            [$response, $requestMeta] = $this->attempt($options);
        } catch (ClientExceptionInterface $e) {
            $this->record($options, null, null, $startedAt, $e->getMessage());
            return [
                'status' => 0,
                'headers' => [],
                'body' => '',
                'request' => $this->buildRequestMetaForRecord($options),
                'durationMs' => (microtime(true) - $startedAt) * 1000,
                'error' => 'NETWORK: ' . $e->getMessage(),
            ];
        }

        $this->record($options, $requestMeta, $response, $startedAt, null);
        return [
            'status' => $response->getStatusCode(),
            'headers' => $this->flattenResponseHeaders($response),
            'body' => (string) $response->getBody(),
            'request' => $requestMeta,
            'durationMs' => (microtime(true) - $startedAt) * 1000,
            'error' => null,
        ];
    }

    /**
     * @param RequestOptions $options
     * @return array{0: ResponseInterface, 1: array{method: string, url: string, headers: array<string, string>, body: ?string}}
     */
    private function attempt(array $options): array
    {
        $url = $this->buildUrl($options['path'], $options['query'] ?? []);

        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
            'X-Api-Key' => $this->apiKey,
        ];
        foreach (($options['headers'] ?? []) as $k => $v) {
            $headers[$k] = $v;
        }
        if (!empty($options['idempotencyKey'])) {
            $headers['Idempotency-Key'] = $options['idempotencyKey'];
        }
        if (!empty($options['requestId'])) {
            $headers['X-Request-Id'] = $options['requestId'];
        }

        $body = null;
        if (array_key_exists('body', $options) && $options['body'] !== null) {
            $body = json_encode($options['body'], JSON_THROW_ON_ERROR);
            $headers['Content-Type'] = 'application/json';
        }

        $response = $this->client->sendRequest(new Request(
            $options['method'],
            $url,
            $headers,
            $body,
        ));

        // Mask the credential before surfacing to the recorder or the proxy
        // passthrough — the demo renders these headers in the browser.
        $maskedHeaders = $headers;
        if (isset($maskedHeaders['X-Api-Key'])) {
            $maskedHeaders['X-Api-Key'] = '***';
        }

        $requestMeta = [
            'method' => $options['method'],
            'url' => $url,
            'headers' => $maskedHeaders,
            'body' => $body,
        ];

        return [$response, $requestMeta];
    }

    /**
     * @param RequestOptions $options
     * @return array{method: string, url: string, headers: array<string, string>, body: null}
     */
    private function buildRequestMetaForRecord(array $options): array
    {
        // Used when attempt() threw before building meta — still give the
        // recorder enough context to place the failed entry.
        return [
            'method' => $options['method'],
            'url' => $this->buildUrl($options['path'], $options['query'] ?? []),
            'headers' => ['X-Api-Key' => '***', 'User-Agent' => $this->userAgent],
            'body' => null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function flattenResponseHeaders(ResponseInterface $response): array
    {
        $flat = [];
        foreach ($response->getHeaders() as $name => $values) {
            $flat[$name] = implode(', ', $values);
        }
        return $flat;
    }

    /**
     * @param RequestOptions $options
     * @param array{method: string, url: string, headers: array<string, string>, body: ?string}|null $requestMeta
     */
    private function record(
        array $options,
        ?array $requestMeta,
        ?ResponseInterface $response,
        float $startedAt,
        ?string $errorMessage,
    ): void {
        if ($this->recorder === null) {
            return;
        }

        $meta = $requestMeta ?? $this->buildRequestMetaForRecord($options);

        $entry = [
            'id' => 'req_' . bin2hex(random_bytes(6)),
            'timestamp' => microtime(true),
            'method' => $meta['method'],
            'url' => $meta['url'],
            'requestHeaders' => $meta['headers'],
            'requestBody' => $meta['body'],
            'status' => $response?->getStatusCode() ?? 0,
            'responseHeaders' => $response !== null ? $this->flattenResponseHeaders($response) : [],
            'responseBody' => $response !== null ? (string) $response->getBody() : '',
            'durationMs' => (microtime(true) - $startedAt) * 1000,
            'error' => $errorMessage !== null ? ('NETWORK: ' . $errorMessage) : null,
            'descriptor' => [
                'method' => $options['method'],
                'path' => $options['path'],
                'query' => $options['query'] ?? null,
                'body' => $options['body'] ?? null,
                'idempotencyKey' => $options['idempotencyKey'] ?? null,
            ],
        ];

        try {
            ($this->recorder)($entry);
        } catch (\Throwable) {
            // Recorder failure must never affect the caller.
        }
    }

    /**
     * @param array<string, scalar|null> $query
     */
    private function buildUrl(string $path, array $query): string
    {
        $base = rtrim($this->baseUrl, '/');
        $path = str_starts_with($path, '/') ? $path : '/' . $path;
        $url = $base . $path;

        $filtered = [];
        foreach ($query as $k => $v) {
            if ($v === null) {
                continue;
            }
            $filtered[$k] = is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
        }
        return $filtered === [] ? $url : $url . '?' . http_build_query($filtered);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJson(ResponseInterface $response): ?array
    {
        $text = (string) $response->getBody();
        if ($text === '') {
            return null;
        }
        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
            return $decoded;
        } catch (\JsonException) {
            return null;
        }
    }

    private function buildApiException(ResponseInterface $response): LogicwareApiException
    {
        $status = $response->getStatusCode();
        $requestId = $response->getHeaderLine('X-Request-Id') ?: null;
        $bodyText = (string) $response->getBody();

        $message = 'HTTP ' . $status;
        $code = null;
        $details = $bodyText;

        if ($bodyText !== '') {
            try {
                /** @var array<string, mixed> $parsed */
                $parsed = json_decode($bodyText, true, flags: JSON_THROW_ON_ERROR);
                $details = $parsed;
                if (isset($parsed['message']) && is_string($parsed['message'])) {
                    $message = $parsed['message'];
                } elseif (isset($parsed['error']) && is_string($parsed['error'])) {
                    $message = $parsed['error'];
                }
                if (isset($parsed['code']) && is_string($parsed['code'])) {
                    $code = $parsed['code'];
                }
            } catch (\JsonException) {
                $snippet = substr($bodyText, 0, 200);
                $message = 'HTTP ' . $status . ': ' . $snippet;
            }
        }

        return new LogicwareApiException($message, $status, $code, $requestId, $details);
    }

    private function isStatusRetryable(int $status): bool
    {
        return $status === 429 || $status === 502 || $status === 503 || $status === 504;
    }

    private function isNetworkRetryable(ClientExceptionInterface $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'timed out') || str_contains($msg, 'connection');
    }

    private function retryAfterMs(ResponseInterface $response): ?int
    {
        $value = $response->getHeaderLine('Retry-After');
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) ((float) $value * 1000);
        }
        $ts = strtotime($value);
        return $ts === false ? null : max(0, ($ts - time()) * 1000);
    }

    private static function backoffMs(int $attempt): int
    {
        $base = 500 * (int) (2 ** ($attempt - 1));
        $jitter = random_int(0, (int) ($base * 0.25));
        return (int) min($base + $jitter, 8_000);
    }

    private function sleep(int $ms): void
    {
        usleep($ms * 1000);
    }
}
