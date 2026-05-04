<?php

declare(strict_types=1);

namespace Logicware\Connect;

use Logicware\Connect\Http\HttpClient;
use Logicware\Connect\Resources\Intake;
use Logicware\Connect\Resources\Manifests;
use Logicware\Connect\Resources\MissingPackages;
use Logicware\Connect\Resources\Packages;
use Logicware\Connect\Resources\PreAlerts;
use Logicware\Connect\Resources\Rates;
use Logicware\Connect\Resources\Shippers;
use Logicware\Connect\Resources\Warehouses;
use Psr\Http\Client\ClientInterface;

/**
 * Root SDK client for Logicware Connect.
 *
 * ```php
 * $client = new \Logicware\Connect\Client([
 *     'apiKey'  => getenv('LW_API_KEY'),
 *     'baseUrl' => 'https://fastship-api.logicware.app',
 * ]);
 *
 * $shipper = $client->shippers->getByEmail('customer@example.com');
 * ```
 *
 * @phpstan-type ClientOptions array{
 *   apiKey: string,
 *   baseUrl: string,
 *   httpClient?: ClientInterface,
 *   timeoutMs?: int,
 *   maxAttempts?: int,
 *   userAgentSuffix?: string,
 *   recorder?: callable(array<string, mixed>): void,
 * }
 */
class Client
{
    public const SDK_VERSION = '0.2.1';
    public const SDK_NAME = 'logicware/connect-sdk';

    public readonly HttpClient $http;

    public readonly Warehouses $warehouses;
    public readonly Shippers $shippers;
    public readonly Packages $packages;
    public readonly Manifests $manifests;
    public readonly PreAlerts $prealerts;
    public readonly Intake $intake;
    public readonly MissingPackages $missingPackages;
    public readonly Rates $rates;

    /**
     * @param ClientOptions $options
     */
    public function __construct(array $options)
    {
        if (empty($options['apiKey'])) {
            throw new \InvalidArgumentException('Client: apiKey is required');
        }
        if (empty($options['baseUrl'])) {
            throw new \InvalidArgumentException('Client: baseUrl is required');
        }

        $this->http = new HttpClient(
            apiKey: $options['apiKey'],
            baseUrl: $options['baseUrl'],
            httpClient: $options['httpClient'] ?? null,
            timeoutMs: $options['timeoutMs'] ?? 30_000,
            maxAttempts: $options['maxAttempts'] ?? 3,
            userAgentSuffix: $options['userAgentSuffix'] ?? null,
            recorder: $options['recorder'] ?? null,
        );

        $this->warehouses = new Warehouses($this->http);
        $this->shippers = new Shippers($this->http);
        $this->packages = new Packages($this->http);
        $this->manifests = new Manifests($this->http);
        $this->prealerts = new PreAlerts($this->http);
        $this->intake = new Intake($this->http);
        $this->missingPackages = new MissingPackages($this->http);
        $this->rates = new Rates($this->http);
    }
}
