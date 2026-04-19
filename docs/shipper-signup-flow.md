# Shipper signup flow

"Bring your own website" in five minutes. Your registration form lives on your domain; one SDK call keeps Logicware in sync.

## The happy path

```php
use Logicware\Connect\Client;

$client = new Client([
    'apiKey'  => getenv('LW_API_KEY'),
    'baseUrl' => getenv('LW_BASE_URL'),
]);

function onShipperSignup(Client $client, array $formData): array
{
    // Step 1: Upsert by email. Idempotent — safe to retry.
    $result = $client->shippers->sync([
        'email' => $formData['email'],
        'name'  => $formData['name'],
        'trn'   => $formData['trn'],
        'phone' => $formData['phone'] ?? null,
    ]);

    // Step 2: Give them a warehouse address code.
    $warehouses = $client->warehouses->list();
    $primary = array_values(array_filter($warehouses, fn ($w) => $w['isDefault'] ?? false))[0]
        ?? $warehouses[0]
        ?? null;
    if ($primary === null) {
        throw new RuntimeException('This courier has no warehouses linked.');
    }

    $client->shippers->addresses->create($result['shipperId'], [
        'warehouseId' => $primary['id'],
        'freightType' => 'Air',
        'isPrimary'   => true,
    ]);

    // Step 3: Return the shipper's public code + the generated warehouse
    // address they'll use at US retailers.
    $detail = $result['detail'] ?? [];
    return [
        'shipperId'   => $result['shipperId'],
        'shipperCode' => $result['shipperCode'],                        // e.g. "FSJ-A3F9K2"
        'addressCode' => $detail['addresses'][0]['addressCode'] ?? null, // e.g. "FSJ-12345"
    ];
}
```

## Why `sync()` instead of `create()`

`sync()` is an **upsert** — if the email is already registered, you get the existing record updated with your latest form data. `create()` would 4xx on a duplicate email.

Use `create()` only when you've checked `getByEmail()` first and confirmed the shipper is genuinely new. Most of the time, `sync()` is what you want.

## Re-creating a lost account

```php
// First time:  status = "created", new shipper ID X, name "Jane Doe"
// Six months later: status = "updated", same shipper ID X, phone now set
$result = $client->shippers->sync([
    'email' => 'jane@example.com',
    'name'  => 'Jane Doe',
    'phone' => '876-555-1234',
]);
// $result['status'] === 'updated'
```

Use `$result['status']` for analytics ("new signups this week" vs "profile updates").

## Bulk import for existing customers

Migrating from another system with thousands of existing customers? Don't loop `sync()` — use `bulkCreate` (auto-chunked at 500 rows) or `importMany` (async, up to 100k):

```php
$shippers = loadLegacyCustomers();  // array<array{email: string, name: string, ...}>

if (count($shippers) < 500) {
    // Synchronous — per-row results in one response
    $result = $client->shippers->bulkCreate($shippers);
    echo "{$result['createdCount']} created, {$result['updatedCount']} updated, {$result['errorCount']} failed\n";
} else {
    // Fire-and-forget — get a job back and poll
    $job = $client->shippers->importMany($shippers);

    foreach ($client->shippers->importProgress($job['jobId']) as $progress) {
        echo "{$progress['processedRows']}/{$progress['totalRows']} processed\n";
    }

    // When done, fetch any failures
    $failures = $client->shippers->getImportFailures($job['jobId'], 0, 100);
    foreach ($failures['failures'] as $f) {
        echo "Row {$f['index']} ({$f['email']}): {$f['errorCode']}\n";
    }
}
```

The `Shippers::BULK_MAX_ROWS` constant (500) is the synchronous-endpoint cap. Anything larger auto-chunks under the hood.

## Error cases to handle

| Code | What it means | What to do |
|---|---|---|
| `INVALID_TRN` | TRN doesn't match the format (Jamaica: 9 digits) | Show field-level validation error |
| `SHIPPER_CODE_CONFLICT` | A `shipperCode` you supplied already belongs to a different email | Surface "that code is taken" or let Logicware auto-generate |
| `EMAIL_REQUIRED` | Empty email | Client-side validation should catch this |
| `NAME_REQUIRED` | Empty name | Client-side validation should catch this |

All other errors surface through `LogicwareApiException::getStatus()`, `::getMessage()`, `::getRequestId()`. See **[error handling](./error-handling.md)**.

## Real-time sync after signup

Packages for a new shipper start arriving at the warehouse almost immediately if they've given their address code to a US retailer. Hook up **[webhooks](./webhooks.md)** so your site shows the first package appearing without the shipper having to refresh.
