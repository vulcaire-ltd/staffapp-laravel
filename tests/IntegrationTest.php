<?php

namespace Vulcaire\StaffApp\Laravel\Tests;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use Vulcaire\StaffApp\Client;
use Vulcaire\StaffApp\Laravel\Facades\StaffApp;
use Vulcaire\StaffApp\Laravel\Http\Middleware\VerifyStaffAppWebhook;
use Vulcaire\StaffApp\Laravel\StaffAppServiceProvider;

final class IntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [StaffAppServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('staffapp.base_url', 'https://staffapp.example');
        $app['config']->set('staffapp.token', 'test-token');
        $app['config']->set('staffapp.webhooks.secrets', ['whsec_'.base64_encode(str_repeat('s', 32))]);
    }

    protected function defineRoutes($router): void
    {
        Route::post('/integration-hook', function (Request $request) {
            return response()->json(['event_id' => $request->attributes->get('staffapp.webhook')->id]);
        })->middleware(VerifyStaffAppWebhook::class);
    }

    public function test_client_configuration_binding_and_scope(): void
    {
        $client = $this->app->make(Client::class);
        self::assertSame($client, $this->app->make(Client::class));
        self::assertSame('https://staffapp.example', $client->__debugInfo()['baseUrl']);
        $this->app->forgetScopedInstances();
        self::assertNotSame($client, $this->app->make(Client::class));
    }

    public function test_facade_uses_the_same_php_client_and_can_be_replaced_for_testing(): void
    {
        $http = new HttpClient(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], '{"data":[{"id":7}]}')]))]);
        $client = new Client('test-token', 'https://staffapp.example', $http);
        $this->app->instance(Client::class, $client);
        self::assertSame([['id' => 7]], StaffApp::jobs()->all()['data']);
    }

    public function test_facade_can_upsert_a_job_through_the_php_client(): void
    {
        $http = new HttpClient(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, [], '{"data":{"id":9,"external_id":"job-9"}}'),
        ]))]);
        $this->app->instance(Client::class, new Client('test-token', 'https://staffapp.example', $http));

        self::assertSame(9, StaffApp::jobs()->upsert([
            'external_id' => 'job-9',
            'source_version' => 1,
            'title' => 'Cover',
        ], 'job-9')['data']['id']);
    }

    public function test_middleware_accepts_signed_event(): void
    {
        $body = '{"type":"worker.change_requested","data":{"worker_id":7}}';
        $timestamp = (string) time();
        $signature = 'v1,'.base64_encode(hash_hmac('sha256', 'evt_1.'.$timestamp.'.'.$body, str_repeat('s', 32), true));
        $this->call('POST', '/integration-hook', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => 'evt_1',
            'HTTP_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => $signature,
        ], content: $body)->assertOk()->assertJsonPath('event_id', 'evt_1');
    }

    public function test_middleware_rejects_unsigned_requests_before_controller_runs(): void
    {
        $this->postJson('/integration-hook', ['type' => 'worker.updated', 'data' => []])->assertUnauthorized();
    }

    public function test_provider_does_not_register_an_automatic_webhook_endpoint(): void
    {
        $this->postJson('/staffapp/webhook')->assertNotFound();
    }

    public function test_configuration_is_publishable(): void
    {
        $paths = StaffAppServiceProvider::pathsToPublish(StaffAppServiceProvider::class, 'staffapp-config');
        self::assertContains(config_path('staffapp.php'), array_values($paths));
    }
}
