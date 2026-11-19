# AssignPoint Laravel client

Laravel 11 and 12 integration for [`vulcaire/staffapp-php`](https://github.com/vulcaire-ltd/staffapp-php).
It adds configuration, container bindings, a facade, a reconcile command and opt-in webhook verification.

It does not read the host application's models, tables or business rules. Any organisation can connect its own AssignPoint credential. The default binding is one credential for the process. A multi-tenant host builds a separate immutable PHP client per organisation from credentials it stores itself. Do not reuse the facade client across organisations.

Requires PHP 8.2 or newer. PHP 8.1 is not supported: Laravel 11 requires 8.2, and the webhook verifier uses readonly classes. The HTTP contract is in the PHP client's README. Calls go to [https://app.assignpoint.com/api](https://app.assignpoint.com/api) (`/api/v1/external` on `https://app.assignpoint.com`).

Licensed under the [MIT License](LICENSE). Report security issues to hello@assignpoint.com. See [SECURITY.md](SECURITY.md).

## Install

```sh
composer require vulcaire/staffapp-laravel:^0.1
php artisan vendor:publish --tag=staffapp-config
```

`vulcaire/staffapp-php` `^0.1` is installed from Packagist with this package. Package discovery registers `StaffAppServiceProvider`. If discovery is disabled, register that provider yourself.

```dotenv
STAFFAPP_BASE_URL=https://app.assignpoint.com
STAFFAPP_API_TOKEN=your-organisation-api-token
STAFFAPP_TIMEOUT=30
STAFFAPP_CONNECT_TIMEOUT=5
```

Configuration works with `php artisan config:cache`. Keep secrets in the environment and protect cached configuration like other application credentials. No URL is assumed when the environment variable is empty. HTTP loopback development also needs `STAFFAPP_ALLOW_INSECURE_LOCALHOST=true`.

## Usage

Examples below are fictional (Acme Ltd, `example.com`).

```php
use Vulcaire\StaffApp\Client;
use Vulcaire\StaffApp\Laravel\Facades\StaffApp;

$jobs = StaffApp::jobs()->all();

function retrieveAssignments(Client $staffapp, int $jobId): array
{
    return $staffapp->jobs()->assignments($jobId)['data'];
}
```

The binding is scoped to the Laravel request or job lifecycle. Tests can replace it with a PHP client that uses a Guzzle `MockHandler`:

```php
$app->instance(Client::class, $client);
```

Record outbound sync intentions alongside your own database changes in a transaction, then process them on your queue. Preserve external IDs, source versions and idempotency keys across attempts. Model observers and field mappings belong in the host application.

## Mirror a host application

The host keeps its own models. This package never reads them. Map only the fields AssignPoint needs. Notes are optional and off unless you pass them.

```php
use Vulcaire\StaffApp\Laravel\Facades\StaffApp;
use Vulcaire\StaffApp\Laravel\Payloads\MirrorPayload;

StaffApp::upsertRole('role-supervisor', 1, MirrorPayload::role('Supervisor', 'SUP'));

StaffApp::upsertWorker(
    'employee-42',
    1,
    MirrorPayload::worker('Sam Example', 'sam@example.com', '+1-202-555-0147'),
    roleExternalIds: ['role-supervisor'],
);

StaffApp::upsertJob(MirrorPayload::job(
    'job-100',
    1,
    'Acme Ltd conference cover',
    '2026-12-01T09:00:00Z',
    '2026-12-01T17:00:00Z',
    [MirrorPayload::jobRole(['role_external_id' => 'role-supervisor', 'quantity_required' => 2])],
    'Main hall',
));

StaffApp::upsertAssignment(MirrorPayload::assignment(
    'alloc-100',
    1,
    'job-100',
    'employee-42',
    'role-supervisor',
));

// After the host's own database transaction commits:
StaffApp::later('assignment', 'alloc-100', 2, MirrorPayload::assignment(
    'alloc-100', 2, 'job-100', 'employee-42', 'role-supervisor',
));
```

`later()` queues `PushMirrorRecord` with exponential backoff (10s, 30s, 90s, 270s). Validation and conflict responses (422 and 409) are not retried. 429, 5xx and transport failures are.

`StaffApp::removeWorker()`, `removeJob()`, `removeAssignment()` and `removeRole()` deactivate or cancel by external id. Removals do not delete the AssignPoint row's history.

### Linking an organisation that already uses AssignPoint

Do not upsert over records that have no external id. Classify first. The class name is yours; `AcmeSource` is only an example.

```php
php artisan staffapp:reconcile App\Integrations\AcmeSource
php artisan staffapp:reconcile App\Integrations\AcmeSource --apply
php artisan staffapp:reconcile App\Integrations\AcmeSource --push
```

The default is a dry run. `--apply` links unambiguous matches and changes no other fields. `--push` links those matches, then queues upserts for matched and new records. Ambiguous and conflicting rows are skipped. The source class implements `Vulcaire\StaffApp\Laravel\Contracts\ReconcileSource` and yields `MirrorRecord` values. The same report is `POST /api/v1/external/reconcile` on `https://app.assignpoint.com/api` with `dry_run: true`.

A record becomes read-only in AssignPoint only when it is linked and either the record's `managed` flag or the organisation setting is on. Unlinking makes it editable again.

## Webhooks

The package registers `POST /staffapp/webhook` (config `staffapp.webhooks.path`). A verified event is dispatched as `Vulcaire\StaffApp\Laravel\Events\WebhookReceived`. Throw from a listener when you could not store the event. AssignPoint will retry. Set `STAFFAPP_WEBHOOK_ROUTE=false` to register the route yourself and keep using `VerifyStaffAppWebhook`.

Set `STAFFAPP_WEBHOOK_SECRET` to the subscription's `whsec_...` secret. Optionally set `STAFFAPP_WEBHOOK_PREVIOUS_SECRET` during rotation. They are separate from the API token.

```php
use Illuminate\Support\Facades\Route;
use Vulcaire\StaffApp\Laravel\Http\Middleware\VerifyStaffAppWebhook;

Route::post('/integrations/staffapp/events', YourWebhookController::class)
    ->middleware(VerifyStaffAppWebhook::class);
```

`YourWebhookController` belongs to the host application. This package does not supply it. Use an API route without browser-session CSRF middleware. If you use web routes, exclude only this exact webhook URI from CSRF and keep signature verification. The middleware returns 401 for a bad signature or a stale or invalid event, and supplies a `VerifiedWebhook` at `$request->attributes->get('staffapp.webhook')` on success. Missing signing configuration fails closed.

The controller must durably record the event before returning success. Check organisation and integration context and the allowed events and fields. Use a unique constraint on subscription plus event ID, accept duplicates as no-ops, queue processing, and apply source and version checks. Signature verification does not prevent replay inside the timestamp window or a business-level conflict. Keep origin and change IDs to prevent loops. Allow a retry when storage fails. Reconcile periodically.

No database migrations or automatic model updates are registered.

## Tests

```sh
composer install
composer test
composer validate --strict
```

`composer install` resolves `vulcaire/staffapp-php` `^0.1` from Packagist. Publish that package before this one.
