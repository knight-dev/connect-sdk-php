# Changelog

All notable changes to `logicware/connect-sdk` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.1] — 2026-04-24

### Changed
- PHPDoc on `Packages::create()` and `Packages::update()` now lists every accepted input key (`packageType`, `freightType`, `condition`, `conditionNotes`, `sourceMarketplace`, `merchantName`, plus the existing dimension fields), so IDE autocomplete and PHPStan see the full V1 surface. Behaviour is unchanged — the SDK passes input arrays through to the API verbatim.

## [0.2.0] — 2026-04-21

### Added
- **Recorder hook** for observability and playground-style demos. Pass a `recorder` callable to the `Client` constructor; the SDK invokes it once per finished request with a structured entry containing `method`, `url`, `requestHeaders` (with `X-Api-Key` masked), `requestBody`, `status`, `responseHeaders`, `responseBody`, `durationMs`, `error`, and the original `descriptor` used for the call (so the entry can be replayed). Defaults to `null` — opt-in, zero overhead for integrators that don't set it.
- `HttpClient::rawRequest()` returns the full pass-through `{status, headers, body, request, durationMs, error}` tuple without the uniform exception mapping. Designed for reverse-proxy flows in the demo playground; production code should continue to use `HttpClient::request()`.

### Changed
- Bumped SDK version to `0.2.0` to reflect the new (still-backwards-compatible) public surface.

## [0.1.1] — 2026-04-20

### Changed
- `Manifests::list()` now returns `['data' => [...], 'pagination' => [...]]` — matches the common V1 envelope returned by every other resource. Previously returned just the raw `data` dict (which had nested `manifests`/`totalCount`/etc). Callers reading `$result['data']` still work; now they also get `$result['pagination']` consistently. This is aligned with the api-courier shape normalization shipped on 2026-04-20.

### Server-side fixes (shipped by api-courier — consumers see them without SDK changes)
- `Package` responses and `package.status_changed` / `package.updated` webhooks now include `freightType` (`'Air'` | `'Sea'`). Access via `$package['freightType']` or `$event['data']['freightType']`.
- Pre-alert list stats are now scoped to the calling courier instead of summing across every courier on the platform.
- Package and Manifest list endpoints now return the common `{ success, data: [...], pagination: {...} }` envelope.

## [0.1.0] — 2026-04-19

First public release. Covers the full v1 "bring your own website" surface.

### Added

**Core client**
- `Logicware\Connect\Client` with `apiKey` + `baseUrl` constructor options.
- `Logicware\Connect\Http\HttpClient` PSR-18 transport with:
  - `X-Api-Key` auth header
  - JSON encoding/decoding
  - Automatic retries on 429 and 5xx with exponential backoff + `Retry-After` support
  - Configurable timeout (default 30s)
  - `Idempotency-Key` header pass-through
  - Client-generated `X-Request-Id` pass-through

**Resources**
- `client->warehouses` — `list()`, `get()`.
- `client->shippers` — `list`, `get`, `getByEmail`, `getByCode`, `create`, `update`, `delete`, `sync` (upsert-by-email), `bulkCreate` (auto-chunks at 500), `importMany` (async up to 100k), `getImport`, `getImportFailures`, `importProgress` (generator).
- `client->shippers->addresses` — full CRUD for secondary addresses.
- `client->packages` — `list`, `get`, `getByTracking`, `update`, `forShipper`, `forManifest`.
- `client->manifests` — `list`, `get`, `create`, `update`, `setOpen`, `close`, `reopen`, `finalize`, `setStatus`, `addPackages`, `removePackage`.
- `client->prealerts` — `list`, `get`, `create`, `cancel`, `lookupByTracking`.
- `client->intake` — `searchUnidentified`, `listUnclaimed`, `listReceived`.
- `client->missingPackages` — `list`, `get`, `create`, `cancel`, `close`.
- `client->rates` — `calculate`.

**Shipper address provisioning** (first-class on every shipper write)
- `addressCode` — the courier's existing label code. Required on create unless `generateAddressCode` is `true`.
- `warehouseId`, `freightType`, `generateAddressCode`, `forceAddressCode` — see the docs.
- Per-row bulk results include `addressCode` + `addressOutcome`.

**Webhooks**
- `Logicware\Connect\Webhooks\Verifier::verify($rawBody, $headers, $secret, $tolerance)` — timing-safe HMAC-SHA256 verification of `X-Logicware-Signature` with 300s replay tolerance on `X-Logicware-Timestamp`. Supports the full event catalog: `package.received`, `package.status_changed`, `package.updated`, `package.deleted`, `manifest.created`, `manifest.closed`, `manifest.reopened`, `prealert.matched`, `prealert.expired`, `intake.unidentified`, `intake.unclaimed`, `missing_package.created`, `missing_package.resolved`.

**Errors**
- `LogicwareException` (base), `LogicwareApiException` (HTTP non-2xx), `LogicwareNetworkException` (transport).
- `WebhookVerificationException` for the verifier path.
- Address-provisioning error codes: `ADDRESS_CODE_REQUIRED`, `ADDRESS_CODE_FORMAT_INVALID`, `ADDRESS_PREFIX_UNKNOWN`, `WAREHOUSE_PREFIX_MISMATCH`, `ADDRESS_CODE_CONFLICT`, `ADDRESS_CODE_IMMUTABLE`, `ADDRESS_CODE_PREFIX_MISMATCH`.

### Tooling
- PHPUnit 10 test suite.
- PHPStan level 8 static analysis.
- php-cs-fixer configuration.

[Unreleased]: https://github.com/knight-dev/connect-sdk-php/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/knight-dev/connect-sdk-php/releases/tag/v0.2.0
[0.1.1]: https://github.com/knight-dev/connect-sdk-php/releases/tag/v0.1.1
[0.1.0]: https://github.com/knight-dev/connect-sdk-php/releases/tag/v0.1.0
