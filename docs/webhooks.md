# Webhooks

Logicware sends HMAC-signed HTTP POSTs to the external webhook URL you configure in the courier portal. The SDK's `Verifier::verify()` validates the signature, checks the timestamp tolerance, parses the envelope, and returns the event data.

## Configure the endpoint

1. In your courier portal, go to **Developer → API keys → Webhooks**.
2. Enter your endpoint URL (e.g. `https://your-courier-site.com/webhooks`).
3. Generate a webhook secret. Save it on your server — this is the key you'll pass to `Verifier::verify()`.

## Receive an event

Use the raw request body — **not** a parsed JSON array. The signature is computed over the exact bytes the server sent.

### Laravel

```php
// routes/web.php
Route::post('/webhooks', WebhookController::class);

// app/Http/Controllers/WebhookController.php
use Illuminate\Http\Request;
use Logicware\Connect\Webhooks\Verifier;
use Logicware\Connect\Webhooks\WebhookVerificationException;

public function __invoke(Request $request)
{
    try {
        $event = Verifier::verify(
            rawBody:   $request->getContent(),  // raw bytes, not ->json()
            signature: $request->header('X-Logicware-Signature'),
            timestamp: $request->header('X-Logicware-Timestamp'),
            secret:    config('services.logicware.webhook_secret'),
        );
    } catch (WebhookVerificationException $e) {
        return response()->json(['error' => $e->errorCode], 401);
    }

    match ($event['event']) {
        'package.received'         => $this->handleReceived($event['data']),
        'package.status_changed'   => $this->handleStatusChanged($event['data']),
        'prealert.matched'         => $this->handlePreAlertMatched($event['data']),
        'missing_package.resolved' => $this->handleMissingResolved($event['data']),
        default                    => null,
    };

    return response()->json(['received' => true]);
}
```

### Slim / plain PSR-7

```php
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Logicware\Connect\Webhooks\Verifier;
use Logicware\Connect\Webhooks\WebhookVerificationException;

function webhookHandler(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
{
    try {
        $event = Verifier::verify(
            rawBody:   (string) $request->getBody(),
            signature: $request->getHeaderLine('X-Logicware-Signature') ?: null,
            timestamp: $request->getHeaderLine('X-Logicware-Timestamp') ?: null,
            secret:    $_ENV['LW_WEBHOOK_SECRET'],
        );
    } catch (WebhookVerificationException $e) {
        $response->getBody()->write(json_encode(['error' => $e->errorCode]));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    // dispatch on $event['event'] ...
    $response->getBody()->write(json_encode(['received' => true]));
    return $response->withHeader('Content-Type', 'application/json');
}
```

## Signing contract

1. **Canonical string**: `"{unixTimestamp}.{rawBody}"`
2. **Algorithm**: HMAC-SHA256
3. **Output**: lowercase hex, sent as `X-Logicware-Signature: sha256={hex}`
4. **Replay protection**: `X-Logicware-Timestamp` header (unix seconds). Reject signatures older than 300s.
5. **Envelope**: JSON object `{ event, timestamp, companyId, companySlug, data }`.

`Verifier::verify()` implements all of this — you shouldn't need to re-implement it.

## Event types

`$event['event']` is one of:

| Event | When |
|---|---|
| `package.received` | Warehouse received a package matching one of your shippers |
| `package.status_changed` | A package's status transitioned |
| `package.updated` | Warehouse edited a package's weight/dimensions/description |
| `package.deleted` | A package was removed |
| `manifest.created` | A new manifest was opened |
| `manifest.closed` | A manifest's `isOpen` flag flipped off |
| `manifest.reopened` | A manifest's `isOpen` flag flipped back on |
| `prealert.matched` | A pre-alert was linked to an arriving intake package |
| `prealert.expired` | A pre-alert passed its expiry without being matched |
| `intake.unclaimed` | Package arrived under a placeholder shipper |
| `missing_package.created` | Shipper filed a "can't find my package" request |
| `missing_package.resolved` | Warehouse marked a missing-package request Found/NotFound |

`$event['data']` is a structured array matching the event type — see each type's fields in the backend's [ExternalCourierEvents](https://github.com/logicware/cargo-connect/blob/main/services/shared/Logicware.Connect.Application/Services/ExternalCourierEventPublisher.cs) catalog.

## Test fixtures

The sandbox page in your courier portal (`/developer/sandbox`) fires synthetic events at any URL. Point it at `https://webhook.site/...` during dev to inspect the exact bytes, then swap to your real endpoint.

See **[error handling](./error-handling.md)** for the `WebhookVerificationException::$errorCode` list.
