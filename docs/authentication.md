# Authentication

The SDK sends your API key in the `X-Api-Key` header on every request. No OAuth, no session cookies, no request signing — just the key.

## Getting a key

1. Sign in to your courier portal.
2. Go to **Developer → API keys**.
3. Click **Create key**, name it, and copy the value **immediately** — it's shown once.

Keys follow the pattern `sk_{env}_{random}` where `{env}` is `live` or `test`.

## Storing the key

The API key is equivalent to your courier's password for the SDK surface. Never:

- Commit it to source control.
- Expose it to the browser.
- Reuse one key across environments.

Do:

- Keep it in `.env` (git-ignored) or a secret manager.
- Rotate on a schedule (every 90 days is reasonable).

## Rotating

The backend supports multiple active keys per courier — zero-downtime rotation:

1. Create a new key in the portal.
2. Deploy the new key to your server.
3. Revoke the old key in the portal.

The portal's **Developer → API keys** view shows `Last used at` per key — use it to confirm traffic has moved.

## Rate limits

Each API key has its own rate-limit bucket:

| Bucket | Limit |
|---|---|
| Regular V1 endpoints | 60 req/min |
| `/api/v1/shippers/bulk` and `/api/v1/shippers/imports` | 10 req/min |

Exceeding the limit returns `429 Too Many Requests` with a `Retry-After` header. The SDK automatically retries up to 3 times with exponential backoff, honoring `Retry-After`.

See the **[error handling guide](./error-handling.md)** for the retry behavior in detail.

## Per-request overrides

Pass `idempotencyKey` or `requestId` with any mutating call — the SDK forwards them as headers. The server echoes `X-Request-Id` on every response, and the SDK surfaces it on `LogicwareApiException::getRequestId()` for support debugging.

```php
// Low-level HTTP for idempotency + request-id pass-through
$response = $client->http->request([
    'method' => 'POST',
    'path'   => '/api/v1/shippers/bulk',
    'body'   => ['shippers' => $shippers],
    'idempotencyKey' => 'shipper-import-batch-42',
    'requestId'      => 'my-app-req-abc123',
]);
```

## Test vs live

Test keys (`sk_test_...`) and live keys (`sk_live_...`) are functionally identical at the SDK layer — the server scopes each key to its environment. Use test keys against staging; live keys in production.
