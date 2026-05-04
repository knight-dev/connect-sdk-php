<?php

declare(strict_types=1);

namespace Logicware\Connect\Tests\Unit;

use GuzzleHttp\Psr7\Response;
use Logicware\Connect\Client;
use Logicware\Connect\Resources\Shippers;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class ResourcesTest extends TestCase
{
    public function testWarehousesListHitsExpectedPath(): void
    {
        $request = null;
        $client = $this->makeClient(function (RequestInterface $req) use (&$request): ResponseInterface {
            $request = $req;
            return new Response(200, [], '{"success":true,"data":[{"id":"w1","name":"Miami"}]}');
        });

        $result = $client->warehouses->list();

        $this->assertNotNull($request);
        $this->assertSame('https://api.test/api/v1/warehouses', (string) $request->getUri());
        $this->assertSame([['id' => 'w1', 'name' => 'Miami']], $result);
    }

    public function testWarehouseGetEncodesId(): void
    {
        $request = null;
        $client = $this->makeClient(function (RequestInterface $req) use (&$request): ResponseInterface {
            $request = $req;
            return new Response(200, [], '{"success":true,"data":{"id":"w 1"}}');
        });

        $client->warehouses->get('w 1');

        $this->assertNotNull($request);
        $this->assertSame('https://api.test/api/v1/warehouses/w%201', (string) $request->getUri());
    }

    public function testShippersListPassesSearchAndPage(): void
    {
        $request = null;
        $client = $this->makeClient(function (RequestInterface $req) use (&$request): ResponseInterface {
            $request = $req;
            return new Response(200, [], '{"success":true,"data":[],"pagination":{"page":2,"pageSize":50,"totalCount":0,"totalPages":0}}');
        });

        $client->shippers->list(['search' => 'acme', 'page' => 2, 'pageSize' => 50]);

        $this->assertNotNull($request);
        $uri = (string) $request->getUri();
        $this->assertStringContainsString('search=acme', $uri);
        $this->assertStringContainsString('page=2', $uri);
        $this->assertStringContainsString('pageSize=50', $uri);
    }

    public function testShippersListClampsPageSizeTo100(): void
    {
        $request = null;
        $client = $this->makeClient(function (RequestInterface $req) use (&$request): ResponseInterface {
            $request = $req;
            return new Response(200, [], '{"success":true,"data":[],"pagination":{"page":1,"pageSize":100,"totalCount":0,"totalPages":0}}');
        });

        $client->shippers->list(['pageSize' => 9999]);

        $this->assertNotNull($request);
        $uri = (string) $request->getUri();
        $this->assertStringContainsString('pageSize=100', $uri);
        $this->assertStringNotContainsString('9999', $uri);
    }

    public function testShippersBulkCreateAutoChunksAndReindexes(): void
    {
        $calls = 0;
        $httpClient = $this->makeHttpClient(function () use (&$calls): ResponseInterface {
            $calls++;
            $size = $calls === 1 ? Shippers::BULK_MAX_ROWS : 50;
            $offset = $calls === 1 ? 0 : Shippers::BULK_MAX_ROWS;
            $results = [];
            for ($i = 0; $i < $size; $i++) {
                $results[] = [
                    'index' => $i,
                    'email' => "u" . ($i + $offset) . "@x.com",
                    'status' => 'created',
                    'shipperId' => "s" . ($i + $offset),
                    'shipperCode' => null,
                    'errorCode' => null,
                    'errorMessage' => null,
                ];
            }
            $body = json_encode([
                'success' => true,
                'data' => [
                    'totalRows' => $size,
                    'createdCount' => $size,
                    'updatedCount' => 0,
                    'errorCount' => 0,
                    'results' => $results,
                ],
            ], JSON_THROW_ON_ERROR);
            return new Response(200, [], $body);
        });

        $client = new Client([
            'apiKey' => 'k',
            'baseUrl' => 'https://api.test',
            'httpClient' => $httpClient,
        ]);

        $inputs = [];
        for ($i = 0; $i < Shippers::BULK_MAX_ROWS + 50; $i++) {
            $inputs[] = ['email' => "u{$i}@x.com", 'name' => "User $i"];
        }

        $result = $client->shippers->bulkCreate($inputs);

        $this->assertSame(2, $calls);
        $this->assertSame(Shippers::BULK_MAX_ROWS + 50, $result['totalRows']);
        $this->assertSame(Shippers::BULK_MAX_ROWS, $result['results'][Shippers::BULK_MAX_ROWS]['index']);
    }

    public function testShipperByEmailAndByCodeEncodePath(): void
    {
        $capturedUris = [];
        $client = $this->makeClient(function (RequestInterface $req) use (&$capturedUris): ResponseInterface {
            $capturedUris[] = (string) $req->getUri();
            return new Response(200, [], '{"success":true,"data":{"id":"s1"}}');
        });

        $client->shippers->getByEmail('a@b.com');
        $client->shippers->getByCode('FSJ-A1B2C3');

        $this->assertSame('https://api.test/api/v1/shippers/by-email/a%40b.com', $capturedUris[0]);
        $this->assertSame('https://api.test/api/v1/shippers/by-code/FSJ-A1B2C3', $capturedUris[1]);
    }

    public function testManifestsAddPackagesSendsArray(): void
    {
        $request = null;
        $client = $this->makeClient(function (RequestInterface $req) use (&$request): ResponseInterface {
            $request = $req;
            return new Response(200, [], '{"success":true,"data":{"addedCount":2,"skippedCount":0,"errors":[]}}');
        });

        $client->manifests->addPackages('m1', ['p1', 'p2']);

        $this->assertNotNull($request);
        $this->assertSame('{"packageIds":["p1","p2"]}', (string) $request->getBody());
    }

    public function testManifestsCloseCallsSetOpenFalse(): void
    {
        $request = null;
        $client = $this->makeClient(function (RequestInterface $req) use (&$request): ResponseInterface {
            $request = $req;
            return new Response(200, [], '{"success":true,"data":{"id":"m1","isOpen":false}}');
        });

        $client->manifests->close('m1');

        $this->assertNotNull($request);
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('/api/v1/manifests/m1/open', (string) $request->getUri());
        $this->assertSame('{"isOpen":false,"autoLinkPackages":false}', (string) $request->getBody());
    }

    public function testPreAlertsLookupUsesQueryParam(): void
    {
        $request = null;
        $client = $this->makeClient(function (RequestInterface $req) use (&$request): ResponseInterface {
            $request = $req;
            return new Response(200, [], '{"success":true,"found":true,"data":{"id":"p1"}}');
        });

        $result = $client->prealerts->lookupByTracking('1Z999');

        $this->assertNotNull($request);
        $this->assertTrue($result['found']);
        $this->assertSame('https://api.test/api/v1/prealerts/lookup?tracking=1Z999', (string) $request->getUri());
    }

    public function testIntakeSearchUnidentifiedRequiresAtLeastOneFilter(): void
    {
        $httpClient = $this->makeHttpClient(fn () => new Response(200, [], '{}'));
        $client = new Client([
            'apiKey' => 'k',
            'baseUrl' => 'https://api.test',
            'httpClient' => $httpClient,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $client->intake->searchUnidentified();
    }

    /**
     * @param callable(RequestInterface): ResponseInterface $handler
     */
    private function makeClient(callable $handler): Client
    {
        return new Client([
            'apiKey' => 'k',
            'baseUrl' => 'https://api.test',
            'httpClient' => $this->makeHttpClient($handler),
        ]);
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
