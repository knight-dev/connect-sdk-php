# Manifest lifecycle

A manifest is a collection of packages shipped together (one plane, one container, one truck). Every package goes on exactly one manifest before it can leave the warehouse.

## The two axes

Manifests have **two independent state flags**:

- **`status`** — lifecycle position: `Draft → Finalized → Shipped → InTransit → AtCustoms → Cleared → Arrived → Completed` (or `Cancelled` from any non-Completed state)
- **`isOpen`** — whether new packages auto-link to this manifest when they arrive. Typically only one manifest per freight type is open at a time.

Close ≠ finalize. "Close" toggles `isOpen` off; "finalize" transitions `status` forward.

## Typical flow

```php
// 1. Create a new Draft manifest.
$m = $client->manifests->create([
    'type'              => 'Air',
    'carrierName'       => 'FedEx',
    'originCode'        => 'MIA',
    'destinationCode'   => 'KIN',
    'estimatedDeparture'=> '2026-05-01T20:00:00Z',
    'estimatedArrival'  => '2026-05-02T08:00:00Z',
    'isOpen'            => true,          // auto-link new packages of this freight type
    'autoLinkPackages'  => true,          // also pull in packages already in the warehouse
]);

// 2. Add more packages manually as they come in (or let auto-link do it).
$client->manifests->addPackages($m['id'], ['pkg-1', 'pkg-2', 'pkg-3']);

// 3. Stop new packages from auto-linking (cutoff time reached).
$client->manifests->close($m['id']);

// 4. Finalize — captures customs + financial data. Packages can't be added after this.
$client->manifests->finalize($m['id'], [
    'customsEntryNumber'  => 'ENTRY-20260501-001',
    'customsExchangeRate' => 155.25,
    'freightChargesUsd'   => 2500,
    'totalDutiesPaid'     => 1200,
    'dutiesCurrency'      => 'JMD',
]);

// 5. Mark shipped when it leaves the dock.
$client->manifests->setStatus($m['id'], [
    'status'          => 'Shipped',
    'actualDeparture' => date('c'),
]);

// 6+. Progress through customs / arrival / completion as events happen.
$client->manifests->setStatus($m['id'], ['status' => 'AtCustoms']);
$client->manifests->setStatus($m['id'], ['status' => 'Cleared']);
$client->manifests->setStatus($m['id'], ['status' => 'Arrived']);
$client->manifests->setStatus($m['id'], ['status' => 'Completed']);
```

## Open / close / reopen semantics

```php
$client->manifests->close($m['id']);               // IsOpen := false, status unchanged
$client->manifests->reopen($m['id']);              // IsOpen := true
$client->manifests->reopen($m['id'], true);        // Also auto-link currently-unassigned packages
```

`reopen()` on a Finalized manifest throws a `LogicwareApiException` — the manifest's status is locked. If you need to add a straggler package to a finalized manifest, you have two options:

1. Add it to the next open manifest instead (the preferred flow).
2. Re-open via status override (warehouse-staff-only, not exposed in the SDK).

## Listing packages on a manifest

The detail endpoint embeds packages, but for manifests with hundreds of packages use pagination:

```php
$page = $client->packages->forManifest($m['id'], ['page' => 1, 'pageSize' => 50]);
echo "{$page['pagination']['totalCount']} packages on manifest\n";
```

## Cancelling

```php
$client->manifests->setStatus($m['id'], [
    'status' => 'Cancelled',
    'notes'  => 'Flight cancelled — packages will move to next air manifest',
]);
```

Cancel works from any pre-Completed status. Packages return to the unassigned pool.

## Delete

Only Draft manifests with zero packages can be hard-deleted:

```php
$client->manifests->delete($m['id']);  // 400 if Finalized, or if any package is attached
```

## Webhook events

| Event | When |
|---|---|
| `manifest.created` | A new manifest was opened |
| `manifest.closed` | `isOpen` flipped to false |
| `manifest.reopened` | `isOpen` flipped to true |

Each payload includes `manifestId`, `manifestNumber`, `type`, `status`, `isOpen`, and `totalPackages`. See **[webhooks](./webhooks.md)**.

## Common mistakes

- **Auto-linking two open manifests of the same type.** The server auto-closes the previous one when you create or reopen a new one with `isOpen: true` — don't fight it.
- **Finalizing before the customs entry number is known.** `customsEntryNumber` is optional; you can finalize without it and then `setStatus(..., ['customsEntryNumber' => ...])` once customs issues it.
- **Assuming finalized = shipped.** Finalizing locks the manifest for package changes but doesn't move the status. You still need a `setStatus(['status' => 'Shipped'])` call when the plane actually leaves.
