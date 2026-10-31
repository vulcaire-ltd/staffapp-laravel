<?php

namespace Vulcaire\StaffApp\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Vulcaire\StaffApp\Webhooks\InvalidWebhookException;
use Vulcaire\StaffApp\Webhooks\WebhookVerifier;

final class VerifyStaffAppWebhook
{
    public function __construct(private readonly WebhookVerifier $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $event = $this->verifier->verify(
                $request->getContent(),
                (string) $request->header('webhook-id', ''),
                (string) $request->header('webhook-timestamp', ''),
                (string) $request->header('webhook-signature', ''),
            );
        } catch (InvalidWebhookException) {
            return new JsonResponse(['message' => 'Invalid webhook.'], 401);
        }
        $request->attributes->set('staffapp.webhook', $event);

        return $next($request);
    }
}
