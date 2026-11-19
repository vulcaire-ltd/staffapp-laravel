<?php

namespace Vulcaire\StaffApp\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Vulcaire\StaffApp\Laravel\StaffAppManager;

/**
 * @method static \Vulcaire\StaffApp\Resources\Jobs jobs()
 * @method static \Vulcaire\StaffApp\Resources\Workers workers()
 * @method static \Vulcaire\StaffApp\Resources\Assignments assignments()
 * @method static \Vulcaire\StaffApp\Resources\Roles roles()
 * @method static array upsertWorker(string $externalId, int $sourceVersion, array $profile, ?string $idempotencyKey = null, ?array $roleExternalIds = null)
 * @method static array upsertJob(array $attributes, ?string $idempotencyKey = null)
 * @method static array upsertAssignment(array $attributes, ?string $idempotencyKey = null)
 * @method static array upsertRole(string $externalId, int $sourceVersion, array $attributes, ?string $idempotencyKey = null)
 * @method static array removeWorker(string $externalId, int $sourceVersion, ?string $idempotencyKey = null)
 * @method static array removeJob(string $externalId, int $sourceVersion, ?string $idempotencyKey = null)
 * @method static array removeAssignment(string $externalId, int $sourceVersion, ?string $idempotencyKey = null, bool $notify = false)
 * @method static array removeRole(string $externalId, int $sourceVersion, ?string $idempotencyKey = null)
 * @method static void later(string $kind, string $externalId, int $sourceVersion, array $attributes = [], bool $remove = false)
 *
 * @see StaffAppManager
 */
final class StaffApp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StaffAppManager::class;
    }
}
