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

```php
// 1. Sync the shipper (upsert by email — idempotent).
$result = $client->shippers->sync([
    'email' => 'customer@example.com',
    'name'  => 'Jane Doe',
    'trn'   => '123456789',
    'phone' => '876-555-1234',
]);
$shipperId = $result['shipperId'];

// 2. Give them a warehouse address code so they can start ordering.
$warehouses = $client->warehouses->list();
$default = array_filter($warehouses, fn ($w) => $w['isDefault'] ?? false)[0] ?? $warehouses[0];
$client->shippers->addresses->create($shipperId, [
    'warehouseId' => $default['id'],
    'freightType' => 'Air',
    'isPrimary'   => true,
]);

// 3. Show the full shipper profile + recent packages.
$shipper  = $client->shippers->get($shipperId);
$packages = $client->packages->forShipper($shipperId, ['pageSize' => 20]);
```

## Next up

- Add **[webhooks](./webhooks.md)** so you get real-time updates instead of polling.
- Learn **[error handling](./error-handling.md)** — every error is a typed exception, and retries are automatic for 429/5xx.
- Read the **[shipper signup flow guide](./shipper-signup-flow.md)** for the full "bring your own website" pattern.
