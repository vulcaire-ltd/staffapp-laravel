<?php

namespace Vulcaire\StaffApp\Laravel;

use Vulcaire\StaffApp\Client;
use Vulcaire\StaffApp\Exceptions\ApiException;

final class Sync
{
    public function __construct(private readonly Client $client) {}

    public function client(): Client
    {
        return $this->client;
    }

    /** @param  array<string, mixed>  $profile
     * @param  list<string>|null  $roleExternalIds */
    public function upsertWorker(string $externalId, int $sourceVersion, array $profile, ?string $idempotencyKey = null, ?array $roleExternalIds = null): array
    {
        return $this->client->workers()->upsert($externalId, $sourceVersion, $profile, $idempotencyKey ?? $this->key('worker', $externalId, $sourceVersion), $roleExternalIds);
    }

    public function removeWorker(string $externalId, int $sourceVersion, ?string $idempotencyKey = null): array
    {
        return $this->client->workers()->deactivate($externalId, $sourceVersion, $idempotencyKey ?? $this->key('worker', $externalId, $sourceVersion, true));
    }

    /** @param  array<string, mixed>  $attributes */
    public function upsertJob(array $attributes, ?string $idempotencyKey = null): array
    {
        return $this->client->jobs()->upsert($attributes, $idempotencyKey ?? $this->key('job', (string) $attributes['external_id'], (int) $attributes['source_version']));
    }

    public function removeJob(string $externalId, int $sourceVersion, ?string $idempotencyKey = null): array
    {
        $job = $this->client->jobs()->findByExternalId($externalId);

        return $this->client->jobs()->cancel((int) $job['data']['id'], $sourceVersion, $idempotencyKey ?? $this->key('job', $externalId, $sourceVersion, true));
    }

    /** @param  array<string, mixed>  $attributes */
    public function upsertAssignment(array $attributes, ?string $idempotencyKey = null): array
    {
        return $this->client->assignments()->upsert($attributes, $idempotencyKey ?? $this->key('assignment', (string) $attributes['external_id'], (int) $attributes['source_version']));
    }

    public function removeAssignment(string $externalId, int $sourceVersion, ?string $idempotencyKey = null, bool $notify = false): array
    {
        return $this->client->assignments()->removeByExternalId($externalId, $sourceVersion, $idempotencyKey ?? $this->key('assignment', $externalId, $sourceVersion, true), $notify);
    }

    /** @param  array<string, mixed>  $attributes */
    public function upsertRole(string $externalId, int $sourceVersion, array $attributes, ?string $idempotencyKey = null): array
    {
        return $this->client->roles()->upsert($externalId, $sourceVersion, $attributes, $idempotencyKey ?? $this->key('role', $externalId, $sourceVersion));
    }

    public function removeRole(string $externalId, int $sourceVersion, ?string $idempotencyKey = null): array
    {
        return $this->client->roles()->deactivate($externalId, $sourceVersion, $idempotencyKey ?? $this->key('role', $externalId, $sourceVersion, true));
    }

    /** @param  list<array<string, mixed>>  $records */
    public function reconcile(array $records, bool $dryRun = true): array
    {
        return $this->client->request('POST', 'reconcile', body: [
            'dry_run' => $dryRun,
            'records' => $records,
        ]);
    }

    /**
     * Dispatch a sync after the host transaction commits. Retries use exponential backoff.
     * 422 and 409 responses are not retried.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function later(string $kind, string $externalId, int $sourceVersion, array $attributes = [], bool $remove = false): void
    {
        PushMirrorRecord::dispatch($kind, $externalId, $sourceVersion, $attributes, $remove, $this->key($kind, $externalId, $sourceVersion, $remove))->afterCommit();
    }

    public function run(string $kind, string $externalId, int $sourceVersion, array $attributes, bool $remove, string $idempotencyKey): array
    {
        try {
            return match ($kind) {
                'worker' => $remove
                    ? $this->removeWorker($externalId, $sourceVersion, $idempotencyKey)
                    : $this->upsertWorker($externalId, $sourceVersion, $attributes['profile'] ?? $attributes, $idempotencyKey, $attributes['role_external_ids'] ?? null),
                'job' => $remove
                    ? $this->removeJob($externalId, $sourceVersion, $idempotencyKey)
                    : $this->upsertJob($attributes, $idempotencyKey),
                'assignment' => $remove
                    ? $this->removeAssignment($externalId, $sourceVersion, $idempotencyKey, (bool) ($attributes['notify'] ?? false))
                    : $this->upsertAssignment($attributes, $idempotencyKey),
                'role' => $remove
                    ? $this->removeRole($externalId, $sourceVersion, $idempotencyKey)
                    : $this->upsertRole($externalId, $sourceVersion, $attributes, $idempotencyKey),
                default => throw new \InvalidArgumentException('Unknown mirror record kind.'),
            };
        } catch (ApiException $exception) {
            if ($exception->status !== 429 && $exception->status < 500) {
                throw $exception;
            }
            throw $exception;
        }
    }

    public function key(string $kind, string $externalId, int $sourceVersion, bool $remove = false): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_.:-]/', '-', $externalId) ?? 'record';

        return 'mirror:'.$kind.':'.$safe.':'.$sourceVersion.':'.($remove ? 'remove' : 'upsert');
    }
}
