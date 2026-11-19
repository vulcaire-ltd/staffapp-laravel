<?php

namespace Vulcaire\StaffApp\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Vulcaire\StaffApp\Laravel\Events\WebhookReceived;
use Vulcaire\StaffApp\Webhooks\InvalidWebhookException;
use Vulcaire\StaffApp\Webhooks\WebhookVerifier;

final class WebhookController
{
    public function __invoke(Request $request, WebhookVerifier $verifier): JsonResponse
    {
        try {
            $event = $verifier->verify(
                $request->getContent(),
                (string) $request->header('webhook-id', ''),
                (string) $request->header('webhook-timestamp', ''),
                (string) $request->header('webhook-signature', ''),
            );
        } catch (InvalidWebhookException|\InvalidArgumentException) {
            return new JsonResponse(['message' => 'Invalid webhook.'], 401);
        }

        event(new WebhookReceived(
            $event->id,
            $event->payload['type'],
            $event->payload['data'],
            $event->payload,
            $event->timestamp,
        ));

        return new JsonResponse(['received' => true]);
    }
}
