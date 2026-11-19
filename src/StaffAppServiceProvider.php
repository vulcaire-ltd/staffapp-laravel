<?php

namespace Vulcaire\StaffApp\Laravel;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Vulcaire\StaffApp\Client;
use Vulcaire\StaffApp\Laravel\Console\ReconcileCommand;
use Vulcaire\StaffApp\Laravel\Http\Controllers\WebhookController;
use Vulcaire\StaffApp\Laravel\StaffAppManager;
use Vulcaire\StaffApp\Laravel\Sync;
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
        $this->app->scoped(Sync::class, fn ($app) => new Sync($app->make(Client::class)));
        $this->app->scoped(StaffAppManager::class, fn ($app) => new StaffAppManager($app->make(Client::class), $app->make(Sync::class)));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/staffapp.php' => config_path('staffapp.php')], 'staffapp-config');
            $this->commands([ReconcileCommand::class]);
        }
        if (config('staffapp.webhooks.route')) {
            Route::post((string) config('staffapp.webhooks.path', 'staffapp/webhook'), WebhookController::class)->name('staffapp.webhook');
        }
    }
}
