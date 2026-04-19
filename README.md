# logicware/connect-sdk

Official PHP SDK for [Logicware Connect](https://logicware.app).

Integrate your own courier website (Laravel, WordPress, Symfony, etc.) with a Logicware-hosted warehouse — sync shippers, handle pre-alerts, receive webhooks.

## Install

```bash
composer require logicware/connect-sdk
```

Requires PHP 8.1+. Any PSR-18 HTTP client works; Guzzle is the suggested default.

## Quickstart

```php
use Logicware\Connect\Client;

$client = new Client([
    'apiKey'  => getenv('LW_API_KEY'),                    // sk_live_... or sk_test_...
    'baseUrl' => 'https://fastship-api.logicware.app',    // your courier's API host
]);

// Resources are added in v0.2 (see SDK_PLAN.md).
```

## Errors

```php
use Logicware\Connect\Exceptions\LogicwareApiException;
use Logicware\Connect\Exceptions\LogicwareNetworkException;

try {
    // ...
} catch (LogicwareApiException $e) {
    echo $e->getStatus();       // 422
    echo $e->getErrorCode();    // SHIPPER_CODE_CONFLICT
    echo $e->getMessage();      // already taken
    echo $e->getRequestId();    // req_abc123
    if ($e->isRetryable()) {
        // 429 / 5xx — the SDK already retried, surface for your own metrics
    }
} catch (LogicwareNetworkException $e) {
    // TLS / DNS / connection reset / timeout
}
```

## Development

```bash
composer install
composer test         # phpunit
composer analyse      # phpstan level 8
composer cs-check     # php-cs-fixer --dry-run
```

## License

MIT
