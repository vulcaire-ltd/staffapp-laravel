<?php

namespace Vulcaire\StaffApp\Laravel;

use Illuminate\Support\ServiceProvider;
use Vulcaire\StaffApp\Client;
use Vulcaire\StaffApp\Webhooks\WebhookVerifier;

final class StaffAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/staffapp.php', 'staffapp');
        $this->app->scoped(Client::class, function ($app): Client {
            $config = $app['config']->get('staffapp', []);

            return new Client(
                token: (string) ($config['token'] ?? ''),
                baseUrl: (string) ($config['base_url'] ?? ''),
                timeout: (float) ($config['timeout'] ?? 30),
                connectTimeout: (float) ($config['connect_timeout'] ?? 5),
                allowInsecureLocalhost: (bool) ($config['allow_insecure_localhost'] ?? false),
            );
        });
        $this->app->scoped(WebhookVerifier::class, fn ($app) => new WebhookVerifier(
            $app['config']->get('staffapp.webhooks.secrets', []),
            (int) $app['config']->get('staffapp.webhooks.tolerance', 300),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/staffapp.php' => config_path('staffapp.php')], 'staffapp-config');
        }
    }
}
