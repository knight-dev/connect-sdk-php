# Shipper signup flow

"Bring your own website" in five minutes. Your registration form lives on your domain; one SDK call keeps Logicware in sync — address and all.

## The happy path

```php
use Logicware\Connect\Client;

$client = new Client([
    'apiKey'  => getenv('LW_API_KEY'),
    'baseUrl' => getenv('LW_BASE_URL'),
]);

function onShipperSignup(Client $client, array $formData): array
{
    // One call upserts the shipper AND provisions their primary warehouse
    // address. Idempotent — safe to retry.
    $result = $client->shippers->sync([
        'email'       => $formData['email'],
        'name'        => $formData['name'],
        'trn'         => $formData['trn'],
        'phone'       => $formData['phone'] ?? null,

        // The label code your courier already prints on packages. The
        // warehouse matches incoming labels against this code, so it has to
        // come from YOUR system at signup time.
        'addressCode' => $formData['addressCode'],
    ]);

    $detail = $result['detail'] ?? [];
    return [
        'shipperId'   => $result['shipperId'],
        'shipperCode' => $result['shipperCode'],
        'addressCode' => $detail['addresses'][0]['addressCode'] ?? null,
    ];
}
```

## Why you need `addressCode` upfront

When packages arrive at the warehouse, they read the label — e.g. `"CNW-12345"` — and route to whichever courier owns the `CNW` prefix. Until we know which shipper that code belongs to, the package sits in the unidentified pool until someone resolves it manually.

The cleanest way to avoid that: pass the code your existing system already uses for this customer at sync time. No manual triage, no gap.

### If you don't have existing codes yet

First-time courier onboarding to Logicware, maybe you've never issued address codes because the warehouse used to generate them? Opt in to auto-generation:

```php
$client->shippers->sync([
    'email'                => 'jane@example.com',
    'name'                 => 'Jane Doe',
    'trn'                  => '123456789',
    'generateAddressCode'  => true,   // platform mints a code using the default warehouse's prefix
]);
```

The generated code comes back in `$result['detail']['addresses'][0]['addressCode']` — store it in your system and use it on labels going forward.

## Multiple warehouses

Your courier has both air and sea warehouses, each with its own prefix? Use whichever prefix matches the code:

```php
// Air warehouse prefix is "CNW", sea warehouse prefix is "FSJ"
$client->shippers->sync([
    'email'       => 'jane@example.com',
    'name'        => 'Jane Doe',
    'trn'         => '123456789',
    'addressCode' => 'CNW-12345',    // platform resolves the prefix → air warehouse
]);
```

You can optionally pin `warehouseId` explicitly, but only if its prefix matches the code — otherwise you'll get `WAREHOUSE_PREFIX_MISMATCH`.

## Replacing an existing shipper's address code

Sometimes an existing customer's code needs to change — maybe they were assigned one before a system cleanup and you want to standardise. `forceAddressCode` lets you replace it, but **only within the same prefix**:

```php
// OK — same prefix, numeric change
$client->shippers->sync([
    'email'             => 'jane@example.com',
    'name'              => 'Jane Doe',
    'trn'               => '123456789',
    'addressCode'       => 'CNW-100',
    'forceAddressCode'  => true,
]);
// Previous "CNW-10001" → now "CNW-100". Existing packages keep their link
// (internal FK, not the string, so no packages lose their owner).

// Blocked — crossing prefixes would silently move this shipper to a
// different warehouse. Add a secondary address via
// $client->shippers->addresses->create() instead.
$client->shippers->sync([
    'email'             => 'jane@example.com',
    'name'              => 'Jane Doe',
    'trn'               => '123456789',
    'addressCode'       => 'FSJ-100',
    'forceAddressCode'  => true,
]);
// → ADDRESS_CODE_PREFIX_MISMATCH
```

Without `forceAddressCode: true`, submitting a different code for an existing shipper returns `ADDRESS_CODE_IMMUTABLE`. That's the safety latch — so a stray typo during a resync can't quietly rewrite an address the warehouse is already printing.

## Re-creating a lost account

A shipper comes back six months later with a new form submission — same email, maybe different phone. `sync()` handles it:

```php
// First time:  status = "created", addressOutcome = "created"
// Six months later, update: status = "updated", addressOutcome = "unchanged"
$result = $client->shippers->sync([
    'email'       => 'jane@example.com',
    'name'        => 'Jane Doe',
    'trn'         => '123456789',
    'phone'       => '876-555-1234',
    'addressCode' => 'CNW-12345',   // same code as before → no-op on the address
]);
```

`status` tells you which shipper branch ran; `addressOutcome` tells you whether the primary address was touched. Use them for analytics ("new signups this week" vs "profile updates" vs "code swaps").

## Bulk import for existing customers

Migrating from another system with thousands of existing customers? Don't loop `sync()` — use `bulkCreate` (auto-chunked at 500 rows) or `importMany` (async, up to 100k). Every row needs an `addressCode` or `generateAddressCode: true`:

```php
$shippers = loadLegacyCustomers();  // array<array{email, name, trn, legacyAddressCode, ...}>

// Typical migration shape
$withCodes = array_map(fn ($s) => [
    'email'       => $s['email'],
    'name'        => $s['name'],
    'trn'         => $s['trn'],
    'phone'       => $s['phone'] ?? null,
    'addressCode' => $s['legacyAddressCode'],   // what the warehouse sees on labels today
], $shippers);

if (count($withCodes) < 500) {
    $result = $client->shippers->bulkCreate($withCodes);
    echo "{$result['createdCount']} created, {$result['updatedCount']} updated, {$result['errorCount']} failed\n";
} else {
    $job = $client->shippers->importMany($withCodes);
    foreach ($client->shippers->importProgress($job['jobId']) as $progress) {
        echo "{$progress['processedRows']}/{$progress['totalRows']} processed\n";
    }
    $failures = $client->shippers->getImportFailures($job['jobId'], 0, 100);
    foreach ($failures['failures'] as $f) {
        echo "Row {$f['index']} ({$f['email']}): {$f['errorCode']}\n";
    }
}
```

Per-row results include `addressCode` and `addressOutcome` so you can tell which rows created an address vs updated one vs left it alone.

## Error cases to handle

| Code | What it means | What to do |
|---|---|---|
| `ADDRESS_CODE_REQUIRED` | First-time sync without `addressCode` or `generateAddressCode` | Collect the code at the form, or opt in to auto-generation |
| `ADDRESS_CODE_FORMAT_INVALID` | Code doesn't match `PREFIX-NNNNN` | Client-side validation on the signup form |
| `ADDRESS_PREFIX_UNKNOWN` | Prefix isn't one of your courier's registered warehouse prefixes | Check `$client->warehouses->list()` to see which prefixes are valid |
| `WAREHOUSE_PREFIX_MISMATCH` | You passed a `warehouseId` whose prefix doesn't match the code | Omit `warehouseId` and let the platform resolve from the prefix |
| `ADDRESS_CODE_CONFLICT` | Code already belongs to a different shipper | Use a different code, or look up the existing shipper |
| `ADDRESS_CODE_IMMUTABLE` | Shipper already has a different code; need `forceAddressCode: true` | Confirm with operator, then resync with the flag |
| `ADDRESS_CODE_PREFIX_MISMATCH` | Force-replace tried to cross prefixes | Use `$client->shippers->addresses->create()` to add a secondary address |
| `INVALID_TRN` | TRN doesn't match the Jamaica 9-digit format | Show field-level validation error |
| `SHIPPER_CODE_CONFLICT` | Explicit `shipperCode` collides with a different email | Use a different code, or let Logicware auto-generate |
| `EMAIL_REQUIRED` / `NAME_REQUIRED` | Empty required field | Client-side validation |

All errors surface through `LogicwareApiException::getStatus()`, `::getMessage()`, `::getRequestId()`. See **[error handling](./error-handling.md)**.

## Real-time sync after signup

Packages for a new shipper start arriving at the warehouse almost immediately if they've given their address code to a US retailer. Hook up **[webhooks](./webhooks.md)** so your site shows the first package appearing without the shipper having to refresh.
