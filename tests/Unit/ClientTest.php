<?php

declare(strict_types=1);

namespace Logicware\Connect\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use Logicware\Connect\Client;
use Logicware\Connect\Exceptions\LogicwareApiException;
use Logicware\Connect\Exceptions\LogicwareNetworkException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class ClientTest extends TestCase
{
    public function testConstructsWithRequiredOptions(): void
    {
        $client = new Client([
            'apiKey' => 'sk_test_xyz',
            'baseUrl' => 'https://dev-api.logicware.app',
        ]);
        $this->assertNotNull($client->http);
    }

    public function testRejectsMissingApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/apiKey/');
        new Client(['apiKey' => '', 'baseUrl' => 'https://x']);
    }

    public function testRejectsMissingBaseUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/baseUrl/');
        new Client(['apiKey' => 'k', 'baseUrl' => '']);
    }

    public function testSendsApiKeyAndUserAgentHeaders(): void
    {
        $capturedRequest = null;
        $httpClient = $this->makeHttpClient(
            function (RequestInterface $req) use (&$capturedRequest): ResponseInterface {
                $capturedRequest = $req;
                return new Response(200, ['Content-Type' => 'application/json'], '{"success":true}');
            }
        );

        $client = new Client([
            'apiKey' => 'sk_test_xyz',
            'baseUrl' => 'https://dev-api.logicware.app',
            'httpClient' => $httpClient,
        ]);

        $client->http->request(['method' => 'GET', 'path' => '/api/v1/warehouses']);

        $this->assertNotNull($capturedRequest);
        $this->assertSame('sk_test_xyz', $capturedRequest->getHeaderLine('X-Api-Key'));
        $this->assertMatchesRegularExpression(
            '#^logicware/connect-sdk/\d+\.\d+\.\d+#',
            $capturedRequest->getHeaderLine('User-Agent')
        );
        $this->assertSame('application/json', $capturedRequest->getHeaderLine('Accept'));
        $this->assertSame('https://dev-api.logicware.app/api/v1/warehouses', (string) $capturedRequest->getUri());
    }

    public function testSerialisesJsonBodyOnPost(): void
    {
        $capturedRequest = null;
        $httpClient = $this->makeHttpClient(
            function (RequestInterface $req) use (&$capturedRequest): ResponseInterface {
                $capturedRequest = $req;
                return new Response(201, [], '{}');
            }
        );

        $client = new Client([
            'apiKey' => 'k',
            'baseUrl' => 'https://x',
            'httpClient' => $httpClient,
        ]);

        $client->http->request([
            'method' => 'POST',
            'path' => '/api/v1/shippers',
            'body' => ['email' => 'a@b.com'],
        ]);

        $this->assertNotNull($capturedRequest);
        $this->assertSame('application/json', $capturedRequest->getHeaderLine('Content-Type'));
        $this->assertSame('{"email":"a@b.com"}', (string) $capturedRequest->getBody());
    }

    public function testEncodesQueryParamsSkippingNull(): void
    {
        $capturedRequest = null;
        $httpClient = $this->makeHttpClient(
            function (RequestInterface $req) use (&$capturedRequest): ResponseInterface {
                $capturedRequest = $req;
                return new Response(200, [], '{}');
            }
        );
        $client = new Client([
            'apiKey' => 'k',
            'baseUrl' => 'https://x',
            'httpClient' => $httpClient,
        ]);

        $client->http->request([
            'method' => 'GET',
            'path' => '/api/v1/shippers',
            'query' => ['search' => 'acme', 'page' => 2, 'archived' => null],
        ]);

        $this->assertNotNull($capturedRequest);
        $this->assertSame('https://x/api/v1/shippers?search=acme&page=2', (string) $capturedRequest->getUri());
    }

    public function testThrowsApiExceptionOn422WithCodeMessageRequestId(): void
    {
        $httpClient = $this->makeHttpClient(
            fn () => new Response(
                422,
                ['X-Request-Id' => 'req_abc123'],
                '{"success":false,"code":"SHIPPER_CODE_CONFLICT","message":"already taken"}'
            )
        );
        $client = new Client([
            'apiKey' => 'k',
            'baseUrl' => 'https://x',
            'httpClient' => $httpClient,
        ]);

        try {
            $client->http->request([
                'method' => 'POST',
                'path' => '/api/v1/shippers/bulk',
                'body' => [],
            ]);
            $this->fail('Expected LogicwareApiException');
        } catch (LogicwareApiException $e) {
            $this->assertSame(422, $e->getStatus());
            $this->assertSame('SHIPPER_CODE_CONFLICT', $e->getErrorCode());
            $this->assertSame('already taken', $e->getMessage());
            $this->assertSame('req_abc123', $e->getRequestId());
            $this->assertFalse($e->isRetryable());
        }
    }

    public function testRetries429ThenSucceeds(): void
    {
        $calls = 0;
        $httpClient = $this->makeHttpClient(function () use (&$calls): ResponseInterface {
            $calls++;
            if ($calls === 1) {
                return new Response(429, ['Retry-After' => '0'], '{"message":"slow down"}');
            }
            return new Response(200, [], '{"ok":true}');
        });

        $client = new Client([
            'apiKey' => 'k',
            'baseUrl' => 'https://x',
            'httpClient' => $httpClient,
            'maxAttempts' => 3,
        ]);

        $result = $client->http->request(['method' => 'GET', 'path' => '/x']);
        $this->assertSame(['ok' => true], $result);
        $this->assertSame(2, $calls);
    }

    public function testWrapsNetworkFailures(): void
    {
        $httpClient = $this->makeHttpClient(
            fn () => throw new class ('connection refused') extends \RuntimeException implements ClientExceptionInterface {}
        );
        $client = new Client([
            'apiKey' => 'k',
            'baseUrl' => 'https://x',
            'httpClient' => $httpClient,
            'maxAttempts' => 1,
        ]);

        $this->expectException(LogicwareNetworkException::class);
        $client->http->request(['method' => 'GET', 'path' => '/x']);
    }

    public function testApiExceptionRetryableFlag(): void
    {
        $this->assertTrue((new LogicwareApiException('x', 429))->isRetryable());
        $this->assertTrue((new LogicwareApiException('x', 500))->isRetryable());
        $this->assertFalse((new LogicwareApiException('x', 404))->isRetryable());
    }

    /**
     * @param callable(RequestInterface): ResponseInterface $handler
     */
    private function makeHttpClient(callable $handler): ClientInterface
    {
        return new class ($handler) implements ClientInterface {
            /** @var callable(RequestInterface): ResponseInterface */
            private $handler;

            public function __construct(callable $handler)
            {
                $this->handler = $handler;
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return ($this->handler)($request);
            }
        };
    }
}
