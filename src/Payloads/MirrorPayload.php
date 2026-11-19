<?php

namespace Vulcaire\StaffApp\Laravel\Payloads;

/**
 * Builds the small set of fields AssignPoint needs.
 * Leave notes null. Do not copy free-text that can contain personal, medical or disciplinary detail.
 */
final class MirrorPayload
{
    /** @return array<string, mixed> */
    public static function worker(string $name, ?string $email = null, ?string $phone = null, bool $active = true): array
    {
        $profile = ['name' => $name, 'active' => $active];
        if ($email !== null && $email !== '') {
            $profile['email'] = $email;
        }
        if ($phone !== null && $phone !== '') {
            $profile['phone'] = $phone;
        }

        return $profile;
    }

    /**
     * @param  list<array<string, mixed>>  $roles
     * @return array<string, mixed>
     */
    public static function job(
        string $externalId,
        int $sourceVersion,
        string $title,
        string $startsAt,
        string $endsAt,
        array $roles,
        ?string $location = null,
        ?string $status = null,
        ?string $notes = null,
    ): array {
        $payload = [
            'external_id' => $externalId,
            'source_version' => $sourceVersion,
            'title' => $title,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'roles' => array_map(fn (array $role): array => self::jobRole($role), $roles),
        ];
        if ($location !== null && $location !== '') {
            $payload['location'] = $location;
        }
        if ($status !== null && $status !== '') {
            $payload['status'] = $status;
        }
        if ($notes !== null && $notes !== '') {
            $payload['notes'] = $notes;
        }

        return $payload;
    }

    /** @param  array<string, mixed>  $role
     * @return array<string, mixed> */
    public static function jobRole(array $role): array
    {
        $clean = [];
        if (isset($role['role_external_id'])) {
            $clean['role_external_id'] = (string) $role['role_external_id'];
        }
        if (isset($role['name'])) {
            $clean['name'] = (string) $role['name'];
        }
        $clean['quantity_required'] = (int) ($role['quantity_required'] ?? 1);
        foreach (['starts_at', 'ends_at'] as $field) {
            if (isset($role[$field]) && $role[$field] !== '') {
                $clean[$field] = (string) $role[$field];
            }
        }

        return $clean;
    }

    /** @return array<string, mixed> */
    public static function role(string $name, ?string $code = null, ?string $colour = null, ?int $defaultPayRate = null): array
    {
        $payload = ['name' => $name];
        if ($code !== null && $code !== '') {
            $payload['code'] = $code;
        }
        if ($colour !== null && $colour !== '') {
            $payload['colour'] = $colour;
        }
        if ($defaultPayRate !== null) {
            $payload['default_pay_rate'] = $defaultPayRate;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    public static function assignment(
        string $externalId,
        int $sourceVersion,
        string $jobExternalId,
        string $workerExternalId,
        ?string $roleExternalId = null,
        bool $notify = false,
    ): array {
        $payload = [
            'external_id' => $externalId,
            'source_version' => $sourceVersion,
            'job_external_id' => $jobExternalId,
            'worker_external_id' => $workerExternalId,
            'notify' => $notify,
        ];
        if ($roleExternalId !== null && $roleExternalId !== '') {
            $payload['role_external_id'] = $roleExternalId;
        }

        return $payload;
    }
}
