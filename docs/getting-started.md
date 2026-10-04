# Getting started

## Install

```bash
composer require logicware/connect-sdk
```

Requires PHP 8.1+. Any PSR-18 HTTP client works; Guzzle is the default and is pulled in as a suggested dependency.

## Configure

You need two things before you can make any call:

1. **An API key.** Generated in the courier portal at `/developer`. Shown once at creation — save it to your secret store.
2. **Your courier's API base URL.** Every courier has its own host — e.g. `https://fastship-api.logicware.app`.

Keep both server-side only.

```php
use Logicware\Connect\Client;

$client = new Client([
    'apiKey'  => getenv('LW_API_KEY'),
    'baseUrl' => getenv('LW_BASE_URL'),
]);
```

## Laravel

Bind the client as a singleton in `AppServiceProvider::register()`:

```php
use Logicware\Connect\Client;

$this->app->singleton(Client::class, fn () => new Client([
    'apiKey'  => config('services.logicware.api_key'),
    'baseUrl' => config('services.logicware.base_url'),
]));
```

Inject `Client $client` into any controller or job.

## Symfony

In `config/services.yaml`:

```yaml
services:
    Logicware\Connect\Client:
        arguments:
            - apiKey: '%env(LW_API_KEY)%'
              baseUrl: '%env(LW_BASE_URL)%'
```

Autowire into controllers via constructor injection.

## First call

List the warehouses your courier is linked to — handy for confirming connectivity and for seeing the address prefixes your shippers will use:

```php
$warehouses = $client->warehouses->list();

foreach ($warehouses as $w) {
    echo "{$w['name']} ({$w['code']}) prefix={$w['addressPrefix']} "
        . "types=" . implode('/', $w['freightTypes']) . "\n";
}
```

Expected output for a freshly-configured courier:

```
Miami Air Hub (MIA-AIR) prefix=FSJ types=Air
Miami Sea Terminal (MIA-SEA) prefix=FSJ-SEA types=Sea
```

## A complete signup flow

Sync the shipper AND provision their primary warehouse address in one call.
The `addressCode` is whatever your own system already uses for this customer —
that's what the warehouse will see on incoming labels.

```php
$result = $client->shippers->sync([
    'email'       => 'customer@example.com',
    'name'        => 'Jane Doe',
    'trn'         => '123456789',
    'phone'       => '876-555-1234',
    'addressCode' => 'CNW-12345',        // your existing label code
]);

echo "Shipper {$result['status']}: {$result['detail']['shipperCode']}\n";
echo "Primary address: {$result['detail']['addresses'][0]['addressCode']}\n";

// Show the shipper's recent packages once they start arriving.
$packages = $client->packages->forShipper($result['shipperId'], ['pageSize' => 20]);
```

If your courier hasn't assigned codes to these customers yet, swap
`addressCode` for `'generateAddressCode' => true` and Logicware will mint one
using your default warehouse prefix. See the
**[shipper signup flow](./shipper-signup-flow.md)** guide for the full
decision matrix, including how to replace an existing code safely via
`forceAddressCode`.

## Next up

- Add **[webhooks](./webhooks.md)** so you get real-time updates instead of polling.
- Learn **[error handling](./error-handling.md)** — every error is a typed exception, and retries are automatic for 429/5xx.
- Read the **[shipper signup flow guide](./shipper-signup-flow.md)** for the full "bring your own website" pattern.
