# Error handling

The SDK throws typed exceptions for every failure mode. No raw Guzzle exceptions, no silent 4xx — catch one of three classes and branch.

## The three exception classes

```php
use Logicware\Connect\Exceptions\LogicwareException;         // base
use Logicware\Connect\Exceptions\LogicwareApiException;      // HTTP non-2xx
use Logicware\Connect\Exceptions\LogicwareNetworkException;  // DNS/TLS/timeout/reset
```

### `LogicwareApiException`

Any non-2xx response. Methods:

| Method | Description |
|---|---|
| `getStatus(): int` | HTTP status code |
| `getErrorCode(): ?string` | Server-emitted error code (e.g. `SHIPPER_CODE_CONFLICT`) |
| `getMessage(): string` | Human-readable message from the server body |
| `getRequestId(): ?string` | Value of `X-Request-Id` response header — include in support tickets |
| `getDetails(): mixed` | Parsed error body (full associative array) |
| `isRetryable(): bool` | `true` for 429 and 5xx — the SDK already retried |

### `LogicwareNetworkException`

The request never reached the server (DNS failure, TCP reset, TLS error, timeout). Wraps the underlying exception in `$e->getPrevious()`.

### `LogicwareException`

Base class — catch this to trap both kinds at once:

```php
try {
    $client->shippers->sync($input);
} catch (LogicwareException $e) {
    // It's from the SDK — handle uniformly.
}
```

## The common pattern

```php
use Logicware\Connect\Exceptions\LogicwareApiException;
use Logicware\Connect\Exceptions\LogicwareNetworkException;

function signupShipper(array $input): array
{
    global $client;

    try {
        return $client->shippers->sync($input);
    } catch (LogicwareApiException $e) {
        error_log("Logicware API {$e->getStatus()} [{$e->getRequestId()}]: {$e->getMessage()}");

        if ($e->getStatus() === 422 && $e->getErrorCode() === 'SHIPPER_CODE_CONFLICT') {
            return ['ok' => false, 'reason' => 'This email is already registered.'];
        }
        if ($e->isRetryable()) {
            // Already retried 3× inside the SDK. Give up and surface.
            return ['ok' => false, 'reason' => 'Service busy. Please try again in a minute.'];
        }
        return ['ok' => false, 'reason' => $e->getMessage()];
    } catch (LogicwareNetworkException $e) {
        return ['ok' => false, 'reason' => 'Cannot reach Logicware right now.'];
    }
}
```

## Automatic retries

The SDK retries without you asking, on:

- **`429 Too Many Requests`** — honors `Retry-After`
- **`502 Bad Gateway`**
- **`503 Service Unavailable`** — honors `Retry-After`
- **`504 Gateway Timeout`**
- Network timeouts and resets (up to `maxAttempts`)

Default policy: 3 attempts, 500ms base delay, exponential backoff with 25% jitter, capped at 8s.

Override per client:

```php
$client = new Client([
    'apiKey'      => '...',
    'baseUrl'     => '...',
    'maxAttempts' => 5,
    'timeoutMs'   => 60_000,
]);
```

## `WebhookVerificationException`

A separate class — not a `LogicwareException` subclass, because it's thrown synchronously from `Verifier::verify()` (not from an HTTP call). Property:

- `$e->errorCode` — one of the constants on `WebhookVerificationException::MISSING_SIGNATURE`, `::MISSING_TIMESTAMP`, `::INVALID_SIGNATURE_FORMAT`, `::SIGNATURE_MISMATCH`, `::TIMESTAMP_OUT_OF_TOLERANCE`, `::INVALID_PAYLOAD`.

Log `MISSING_SIGNATURE` or `INVALID_SIGNATURE_FORMAT` at warn level — they usually mean something other than Logicware is hitting your endpoint.
`SIGNATURE_MISMATCH` or `TIMESTAMP_OUT_OF_TOLERANCE` should **page** on repeat — they mean your webhook secret is out of sync or your clock is skewed.
