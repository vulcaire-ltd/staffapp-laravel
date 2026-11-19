<?php

namespace Vulcaire\StaffApp\Laravel;

/** One host record to classify or push. Attributes must contain only fields AssignPoint accepts. */
final class MirrorRecord
{
    /** @param  array<string, mixed>  $attributes */
    public function __construct(
        public readonly string $kind,
        public readonly string $externalId,
        public readonly int $sourceVersion,
        public readonly array $attributes = [],
        public readonly bool $remove = false,
        public readonly ?int $assignpointId = null,
    ) {}

    /** @return array<string, mixed> */
    public function identity(): array
    {
        return array_filter([
            'type' => $this->kind,
            'external_id' => $this->externalId,
            'source_version' => $this->sourceVersion,
            'id' => $this->assignpointId,
            'name' => $this->attributes['name'] ?? ($this->attributes['profile']['name'] ?? null),
            'code' => $this->attributes['code'] ?? null,
            'email' => $this->attributes['email'] ?? ($this->attributes['profile']['email'] ?? null),
            'phone' => $this->attributes['phone'] ?? ($this->attributes['profile']['phone'] ?? null),
            'title' => $this->attributes['title'] ?? null,
            'job_external_id' => $this->attributes['job_external_id'] ?? null,
            'worker_external_id' => $this->attributes['worker_external_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
