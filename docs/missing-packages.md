# Missing package requests

A shipper says "my package should have arrived but I don't see it." The SDK lets your website file a missing-package request on their behalf, track it through resolution, and receive a webhook when the warehouse confirms whether it was found.

## File a request

Exposed via `$client->missingPackages->create()`. Every request is scoped to a specific shipper and a specific warehouse:

```php
$req = $client->missingPackages->create([
    'shipperId'           => $shipper['id'],
    'warehouseLocationId' => $warehouse['id'],   // from $client->warehouses->list()
    'trackingNumber'      => '1Z999AA10123456784',
    'carrier'             => 'UPS',
    'merchantName'        => 'Amazon',
    'orderNumber'         => '112-4829103-8847211',
    'shippedDate'         => '2026-04-15',
    'expectedArrivalDate' => '2026-04-22',
    'estimatedWeightLbs'  => 2.5,
    'declaredValueUsd'    => 120,
    'notes'               => 'Tracking shows delivered to warehouse on April 20',
    'isUrgent'            => false,
]);

echo "Request {$req['id']} filed — status: {$req['status']}\n";
```

The warehouse staff see the request in their queue, search for the package, and resolve it as `Found` (linked to an intake package) or `NotFound`.

## Listing and filtering

```php
// All open requests for your shippers
$open = $client->missingPackages->list([
    'status'   => 'Pending',
    'page'     => 1,
    'pageSize' => 25,
]);

// Urgent requests only
$urgent = $client->missingPackages->list(['priority' => 'Urgent']);
```

Filter values:

| Field | Valid values |
|---|---|
| `status` | `Pending`, `Searching`, `Found`, `NotFound`, `Cancelled`, `Expired`, `Closed` |
| `priority` | `Normal`, `High`, `Urgent` |

## Getting a single request

```php
$req = $client->missingPackages->get($requestId);
echo "{$req['status']} — {$req['daysPending']} days pending\n";
```

The server enforces that a request must belong to your courier — you'll get a 404 for another courier's requests.

## Cancel or close

```php
// Shipper realized they entered the wrong tracking number
$client->missingPackages->cancel($req['id'], 'Wrong tracking — re-filing with correct number');

// Warehouse found it, shipper confirmed receipt, time to close the ticket
$client->missingPackages->close($req['id'], 'Package delivered to shipper on 2026-04-25');
```

## The resolution webhook

The warehouse marks the request `Found` or `NotFound` in their UI. You get notified:

```php
// Inside your webhook handler
if ($event['event'] === 'missing_package.resolved') {
    $data = $event['data'];
    $requestId          = $data['requestId'];
    $resolution         = $data['resolution'];              // 'Found' | 'NotFound'
    $matchedPackageId   = $data['matchedIntakePackageId'];
    $resolutionNotes    = $data['resolutionNotes'];

    if ($resolution === 'Found' && $matchedPackageId !== null) {
        // Notify the shipper — their package was located!
        notifyShipperOfFoundPackage($requestId, $matchedPackageId);
    } elseif ($resolution === 'NotFound') {
        // Warehouse searched, couldn't find it. Refund / escalate?
        escalateToSupport($requestId, $resolutionNotes);
    }
}
```

See **[webhooks](./webhooks.md)** for the full event handling pattern.

## Urgency and priority

`isUrgent: true` on creation maps to `priority: 'Urgent'` on the request. Warehouse queues are typically worked priority-first, so use this sparingly — for customers whose package is stuck and they're about to churn, not as a default.

If your staff need to bump priority after filing, that's done in the warehouse portal today; the SDK doesn't expose priority updates in v1 (tracked for v1.1).

## Cases the SDK rejects server-side

- **Missing `shipperId` or `warehouseLocationId`** — returns 400 with a descriptive error code.
- **Shipper from a different courier** — 404. Your courier's API key can't file a request on another courier's shipper (physical tenant isolation).
- **Warehouse not linked** — 400 `WAREHOUSE_NOT_LINKED` for a `warehouseLocationId` that isn't in your courier's `FreightCompanyWarehouse` table.

Always pull the valid warehouse list from `$client->warehouses->list()` before showing the form, rather than hardcoding IDs.
