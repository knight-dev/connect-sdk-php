# Logicware Connect SDK — PHP

The `logicware/connect-sdk` Composer package lets an external courier website integrate with a Logicware-hosted warehouse from any PHP app (Laravel, Symfony, Slim, or plain PHP). Your site becomes the system of record for shipper signup, pre-alerts, and customer-facing tracking; Logicware handles the physical intake, routing, and manifesting.

## Guides

- **[Getting started](./getting-started.md)** — install, configure, hit your first endpoint.
- **[Authentication](./authentication.md)** — API keys, rotation, scopes, rate limits.
- **[Webhooks](./webhooks.md)** — receive signed events, verify signatures, handle each event type.
- **[Error handling](./error-handling.md)** — exception classes, retry semantics, request IDs.
- **[Shipper signup flow](./shipper-signup-flow.md)** — hooking your registration form into `$client->shippers->sync()`.
- **[Manifest lifecycle](./manifest-lifecycle.md)** — create → open/close → finalize → shipped → completed.
- **[Intake handling](./intake-handling.md)** — unidentified search, unclaimed list, received-since polling.
- **[Missing package requests](./missing-packages.md)** — file, track, resolve on behalf of shippers.

## API reference

Generated from the PHPDoc by phpDocumentor:

```bash
composer require --dev phpdocumentor/phpdocumentor
./vendor/bin/phpdoc -d src/ -t docs-build/
```

## Identical contracts

The PHP SDK exposes the same method signatures, same resource names, and same error codes as the JS SDK. If you're reading the JS docs and adapting to PHP, every `client.X.Y()` translates to `$client->X->Y()`.
