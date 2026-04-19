# Changelog

All notable changes to `logicware/connect-sdk` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Shipper create/sync/bulk/import inputs accept address-provisioning fields:
  `addressCode` (the courier's existing label code — required on create unless
  `generateAddressCode` is `true`), `warehouseId`, `freightType`,
  `generateAddressCode` (opt-in auto-generate using the default warehouse),
  and `forceAddressCode` (opt-in replace; same-prefix only).
- Per-row bulk results now include `addressCode` and `addressOutcome`
  (`'created' | 'updated' | 'unchanged'`).
- New error codes surfaced via `LogicwareApiException::getErrorCode()`:
  `ADDRESS_CODE_REQUIRED`, `ADDRESS_CODE_FORMAT_INVALID`,
  `ADDRESS_PREFIX_UNKNOWN`, `WAREHOUSE_PREFIX_MISMATCH`,
  `ADDRESS_CODE_CONFLICT`, `ADDRESS_CODE_IMMUTABLE`,
  `ADDRESS_CODE_PREFIX_MISMATCH`.

### Changed
- First-time `shippers->sync()` / `create()` / `bulkCreate()` calls now
  provision the shipper's primary address in the same round-trip — no
  separate `shippers->addresses->create()` step needed. The manual CRUD
  endpoint stays available for secondary addresses.

## [0.1.0] — 2026-04-18

### Added
- Initial scaffold release.
- `Logicware\Connect\Client` root client with `apiKey` + `baseUrl` constructor options.
- `Logicware\Connect\Http\HttpClient` PSR-18 transport with:
  - `X-Api-Key` auth header
  - JSON encoding/decoding
  - Automatic retries on 429 and 5xx with exponential backoff + `Retry-After` support
  - Configurable timeout (default 30s)
  - `Idempotency-Key` header pass-through
  - Client-generated `X-Request-Id` pass-through
- `LogicwareException`, `LogicwareApiException`, `LogicwareNetworkException`.
- PHPUnit 10 test suite.
- PHPStan level 8 static analysis configuration.

### Not yet

Resources (shippers, packages, manifests, pre-alerts, intake, missing packages, warehouses, webhook verify) ship in `0.2.0`.

[Unreleased]: https://github.com/knight-dev/connect-sdk-php/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/knight-dev/connect-sdk-php/releases/tag/v0.1.0
