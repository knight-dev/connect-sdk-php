<?php

declare(strict_types=1);

namespace Logicware\Connect\Resources;

use Generator;
use Logicware\Connect\Http\HttpClient;

class Shippers extends ResourceBase
{
    public const BULK_MAX_ROWS = 500;

    public readonly ShipperAddresses $addresses;

    public function __construct(HttpClient $http)
    {
        parent::__construct($http);
        $this->addresses = new ShipperAddresses($http);
    }

    /**
     * @param array{search?: string, page?: int, pageSize?: int} $options
     * @return array{data: list<array<string, mixed>>, pagination: array<string, int>}
     */
    public function list(array $options = []): array
    {
        $query = $this->buildPageQuery($options['page'] ?? null, $options['pageSize'] ?? null);
        if (isset($options['search'])) {
            $query['search'] = $options['search'];
        }
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/shippers',
            'query' => $query,
        ]);
        return ['data' => $raw['data'] ?? [], 'pagination' => $raw['pagination'] ?? []];
    }

    /**
     * @param array{search?: string, pageSize?: int} $options
     * @return Generator<int, array<string, mixed>, void, void>
     */
    public function listAll(array $options = []): Generator
    {
        return $this->paginate(fn (int $page) => $this->list(array_merge($options, ['page' => $page])));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/shippers/' . rawurlencode($id),
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getByEmail(string $email): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/shippers/by-email/' . rawurlencode($email),
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getByCode(string $code): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/shippers/by-code/' . rawurlencode($code),
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @param array{email: string, name: string, trn: string, phone?: string, addressLine1?: string, addressLine2?: string, city?: string, parish?: string} $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/shippers',
            'body' => $input,
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @param array<string, scalar> $input
     * @return array<string, mixed>
     */
    public function update(string $id, array $input): array
    {
        $raw = $this->http->request([
            'method' => 'PUT',
            'path' => '/api/v1/shippers/' . rawurlencode($id),
            'body' => $input,
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * Auto-chunks beyond 500 rows and merges per-row results with stable indexing.
     *
     * @param list<array<string, mixed>> $inputs
     * @return array{totalRows: int, createdCount: int, updatedCount: int, errorCount: int, results: list<array<string, mixed>>}
     */
    public function bulkCreate(array $inputs): array
    {
        if (count($inputs) <= self::BULK_MAX_ROWS) {
            return $this->bulkChunk($inputs);
        }

        $merged = [
            'totalRows' => 0,
            'createdCount' => 0,
            'updatedCount' => 0,
            'errorCount' => 0,
            'results' => [],
        ];
        foreach (array_chunk($inputs, self::BULK_MAX_ROWS) as $chunkIndex => $chunk) {
            $result = $this->bulkChunk($chunk);
            $merged['totalRows'] += $result['totalRows'];
            $merged['createdCount'] += $result['createdCount'];
            $merged['updatedCount'] += $result['updatedCount'];
            $merged['errorCount'] += $result['errorCount'];
            $offset = $chunkIndex * self::BULK_MAX_ROWS;
            foreach ($result['results'] as $row) {
                $row['index'] = ($row['index'] ?? 0) + $offset;
                $merged['results'][] = $row;
            }
        }
        return $merged;
    }

    /**
     * @param list<array<string, mixed>> $chunk
     * @return array{totalRows: int, createdCount: int, updatedCount: int, errorCount: int, results: list<array<string, mixed>>}
     */
    private function bulkChunk(array $chunk): array
    {
        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/shippers/bulk',
            'body' => ['shippers' => $chunk],
        ]);
        /** @var array{totalRows: int, createdCount: int, updatedCount: int, errorCount: int, results: list<array<string, mixed>>} $data */
        $data = $raw['data'] ?? ['totalRows' => 0, 'createdCount' => 0, 'updatedCount' => 0, 'errorCount' => 0, 'results' => []];
        return $data;
    }

    /**
     * @param list<array<string, mixed>> $inputs
     * @return array{jobId: string, statusUrl: string, totalRows: int}
     */
    public function importMany(array $inputs): array
    {
        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/shippers/imports',
            'body' => ['shippers' => $inputs],
        ]);
        /** @var array{jobId: string, statusUrl: string, totalRows: int} $data */
        $data = $raw['data'] ?? ['jobId' => '', 'statusUrl' => '', 'totalRows' => 0];
        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getImport(string $jobId): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/shippers/imports/' . rawurlencode($jobId),
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getImportFailures(string $jobId, int $offset = 0, int $limit = 100): array
    {
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/api/v1/shippers/imports/' . rawurlencode($jobId) . '/failures',
            'query' => ['offset' => $offset, 'limit' => $limit],
        ]);
        return $raw['data'] ?? [];
    }

    /**
     * Poll until the import reaches a terminal state. Yields progress snapshots.
     *
     * @return Generator<int, array<string, mixed>, void, array<string, mixed>>
     */
    public function importProgress(string $jobId, int $intervalMs = 2000, ?int $timeoutMs = null): Generator
    {
        $deadline = $timeoutMs !== null ? (int) (microtime(true) * 1000) + $timeoutMs : null;
        while (true) {
            $snap = $this->getImport($jobId);
            yield $snap;
            $status = (string) ($snap['status'] ?? '');
            if (in_array($status, ['Completed', 'PartialSuccess', 'Failed', 'Cancelled'], true)) {
                return $snap;
            }
            if ($deadline !== null && (int) (microtime(true) * 1000) >= $deadline) {
                return $snap;
            }
            usleep($intervalMs * 1000);
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function sync(array $input): array
    {
        $raw = $this->http->request([
            'method' => 'POST',
            'path' => '/api/v1/shippers/sync',
            'body' => $input,
        ]);
        return $raw['data'] ?? [];
    }
}
