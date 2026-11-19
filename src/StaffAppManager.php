<?php

namespace Vulcaire\StaffApp\Laravel;

use Vulcaire\StaffApp\Client;
use Vulcaire\StaffApp\Resources\Assignments;
use Vulcaire\StaffApp\Resources\Jobs;
use Vulcaire\StaffApp\Resources\Roles;
use Vulcaire\StaffApp\Resources\Workers;

final class StaffAppManager
{
    public function __construct(private readonly Client $client, private readonly Sync $sync) {}

    public function client(): Client
    {
        return $this->client;
    }

    public function jobs(): Jobs
    {
        return $this->client->jobs();
    }

    public function workers(): Workers
    {
        return $this->client->workers();
    }

    public function assignments(): Assignments
    {
        return $this->client->assignments();
    }

    public function roles(): Roles
    {
        return $this->client->roles();
    }

    public function sync(): Sync
    {
        return $this->sync;
    }

    /** @param  array<string, mixed>  $profile
     * @param  list<string>|null  $roleExternalIds */
    public function upsertWorker(string $externalId, int $sourceVersion, array $profile, ?string $idempotencyKey = null, ?array $roleExternalIds = null): array
    {
        return $this->sync->upsertWorker($externalId, $sourceVersion, $profile, $idempotencyKey, $roleExternalIds);
    }

    public function removeWorker(string $externalId, int $sourceVersion, ?string $idempotencyKey = null): array
    {
        return $this->sync->removeWorker($externalId, $sourceVersion, $idempotencyKey);
    }

    /** @param  array<string, mixed>  $attributes */
    public function upsertJob(array $attributes, ?string $idempotencyKey = null): array
    {
        return $this->sync->upsertJob($attributes, $idempotencyKey);
    }

    public function removeJob(string $externalId, int $sourceVersion, ?string $idempotencyKey = null): array
    {
        return $this->sync->removeJob($externalId, $sourceVersion, $idempotencyKey);
    }

    /** @param  array<string, mixed>  $attributes */
    public function upsertAssignment(array $attributes, ?string $idempotencyKey = null): array
    {
        return $this->sync->upsertAssignment($attributes, $idempotencyKey);
    }

    public function removeAssignment(string $externalId, int $sourceVersion, ?string $idempotencyKey = null, bool $notify = false): array
    {
        return $this->sync->removeAssignment($externalId, $sourceVersion, $idempotencyKey, $notify);
    }

    /** @param  array<string, mixed>  $attributes */
    public function upsertRole(string $externalId, int $sourceVersion, array $attributes, ?string $idempotencyKey = null): array
    {
        return $this->sync->upsertRole($externalId, $sourceVersion, $attributes, $idempotencyKey);
    }

    public function removeRole(string $externalId, int $sourceVersion, ?string $idempotencyKey = null): array
    {
        return $this->sync->removeRole($externalId, $sourceVersion, $idempotencyKey);
    }

    /** @param  array<string, mixed>  $attributes */
    public function later(string $kind, string $externalId, int $sourceVersion, array $attributes = [], bool $remove = false): void
    {
        $this->sync->later($kind, $externalId, $sourceVersion, $attributes, $remove);
    }
}
