<?php

namespace Vulcaire\StaffApp\Laravel\Events;

/** Fired after the signature is verified. Listeners that throw ask AssignPoint to retry. */
final class WebhookReceived
{
    /** @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $payload */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $data,
        public readonly array $payload,
        public readonly int $timestamp,
    ) {}
}
