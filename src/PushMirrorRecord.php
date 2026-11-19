<?php

namespace Vulcaire\StaffApp\Laravel;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Vulcaire\StaffApp\Exceptions\ApiException;
use Vulcaire\StaffApp\Exceptions\TransportException;

class PushMirrorRecord implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @param  array<string, mixed>  $attributes */
    public function __construct(
        public readonly string $kind,
        public readonly string $externalId,
        public readonly int $sourceVersion,
        public readonly array $attributes = [],
        public readonly bool $remove = false,
        public readonly ?string $idempotencyKey = null,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return RetryAfter::seconds($this->tries);
    }

    public function handle(Sync $sync): void
    {
        try {
            $sync->run($this->kind, $this->externalId, $this->sourceVersion, $this->attributes, $this->remove, $this->idempotencyKey ?? $sync->key($this->kind, $this->externalId, $this->sourceVersion, $this->remove));
        } catch (ApiException $exception) {
            if ($exception->status === 429 || $exception->status >= 500) {
                throw $exception;
            }
        } catch (TransportException $exception) {
            throw $exception;
        }
    }
}
