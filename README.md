# StaffApp Laravel

Laravel 12/13 integration for `vulcaire/staffapp-php` (PHP 8.3+). This package adds
configuration, container bindings, a facade and opt-in webhook signature middleware.
It does not depend on consumer models, tables, queues, controllers or business rules.

**Development preview; not published.** PHP 8.2+ and Laravel 11, 12 or 13.
PHP 8.1 is not supported because Laravel 11 requires 8.2 and the webhook verifier
uses readonly classes. See the PHP client's README for the HTTP contract.

## Install

After publication (installs the PHP client automatically):

```sh
composer require vulcaire/staffapp-laravel:^0.1
php artisan vendor:publish --tag=staffapp-config
```

Before publication use path repositories for both packages; see the development
workspace README. Package discovery registers `StaffAppServiceProvider` automatically.
If discovery is disabled, register that provider in the host's providers list.

```dotenv
STAFFAPP_BASE_URL=https://staffapp.example
STAFFAPP_API_TOKEN=your-business-api-token
STAFFAPP_TIMEOUT=30
STAFFAPP_CONNECT_TIMEOUT=5
```

Configuration works with `php artisan config:cache`; keep secrets in environment
configuration and protect cached configuration like other application credentials.
No URL is silently assumed. HTTP loopback development also needs
`STAFFAPP_ALLOW_INSECURE_LOCALHOST=true`.

## Usage

```php
use Vulcaire\StaffApp\Client;
use Vulcaire\StaffApp\Laravel\Facades\StaffApp;

$jobs = StaffApp::jobs()->all();

// Equivalently, use constructor/method injection in an application service:
function retrieveAssignments(Client $staffapp, int $jobId): array
{
    return $staffapp->jobs()->assignments($jobId)['data'];
}
```

The binding is scoped to the Laravel request/job lifecycle. A deployment's default
configuration represents **one integration credential**. Multi-tenant hosts should
build separate immutable PHP clients from their own securely stored credentials;
do not mutate global configuration or reuse a facade client across business accounts.
Tests can bind a PHP Client with a Guzzle MockHandler via `$app->instance(Client::class, $client)`.

Record outbound sync intentions alongside your own database changes in a transaction,
then process them in your chosen queue. Preserve external IDs, source versions and
idempotency keys across attempts. Model observers and data mappings belong in the
consuming application. This package never scans or modifies an application's workers.

## Mirror a host app

The host keeps its own models. This package never reads them. Map only the fields
AssignPoint needs. Notes are optional and off unless you pass them.

```php
use Vulcaire\StaffApp\Laravel\Facades\StaffApp;
use Vulcaire\StaffApp\Laravel\Payloads\MirrorPayload;

StaffApp::upsertRole('role-supervisor', 1, MirrorPayload::role('Supervisor', 'SUP'));

StaffApp::upsertWorker(
    'employee-42',
    1,
    MirrorPayload::worker('Sam Example', 'sam@example.com', '+447700900123'),
    roleExternalIds: ['role-supervisor'],
);

StaffApp::upsertJob(MirrorPayload::job(
    'job-100',
    1,
    'Conference cover',
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

`later()` queues `PushMirrorRecord` with exponential backoff (10s, 30s, 90s, 270s).
Validation and conflict responses (422 and 409) are not retried. 429, 5xx and
transport failures are.

`StaffApp::removeWorker()`, `removeJob()`, `removeAssignment()` and `removeRole()`
deactivate or cancel by external id. Removals do not delete the AssignPoint row's
history.

### Linking an organisation that already uses AssignPoint

Do not upsert over records that have no external id. Classify first:

```php
php artisan staffapp:reconcile App\Integrations\AssignPointSource
php artisan staffapp:reconcile App\Integrations\AssignPointSource --apply
php artisan staffapp:reconcile App\Integrations\AssignPointSource --push
```

The default is a dry run. `--apply` links unambiguous matches and changes no other
fields. `--push` links those matches, then queues upserts for matched and new
records. Ambiguous and conflicting rows are skipped. The source class implements
`Vulcaire\StaffApp\Laravel\Contracts\ReconcileSource` and yields `MirrorRecord`
values. The same report is `POST /api/v1/external/reconcile` with `dry_run: true`.

A record becomes read-only in AssignPoint only when it is linked and either the
record's `managed` flag or the organisation setting is on. Unlinking is audited
and makes it editable again.

## Opt-in webhooks

The package registers `POST /staffapp/webhook` (config `staffapp.webhooks.path`).
A verified event is dispatched as `Vulcaire\StaffApp\Laravel\Events\WebhookReceived`.
Throw from a listener when you could not store the event; AssignPoint will retry.
Set `STAFFAPP_WEBHOOK_ROUTE=false` to register the route yourself and keep using
`VerifyStaffAppWebhook`.

## Previous webhook notes

Set `STAFFAPP_WEBHOOK_SECRET` to the subscription's `whsec_...` secret; optionally
set `STAFFAPP_WEBHOOK_PREVIOUS_SECRET` during rotation. They are separate from API
credentials. Register a POST route yourself in an API route group:

```php
use Illuminate\Support\Facades\Route;
use Vulcaire\StaffApp\Laravel\Http\Middleware\VerifyStaffAppWebhook;

Route::post('/integrations/staffapp/events', YourWebhookController::class)
    ->middleware(VerifyStaffAppWebhook::class);
```

`YourWebhookController` is a host-application controller, not supplied by this package.
Use an API route without browser-session CSRF middleware. If using web routes,
exclude only this exact webhook URI from CSRF, keeping signature verification.
The middleware returns 401 for bad signatures/stale or invalid events and supplies
a `VerifiedWebhook` at `$request->attributes->get('staffapp.webhook')` on success.
Missing signing configuration fails closed rather than accepting requests.

**Your controller must durably record the event before returning success.** Check
organisation/integration context and allowed events/fields. Use a unique constraint
on subscription + event ID, accept duplicates as no-ops, queue processing and apply
source/version checks. Signature verification alone does not prevent replay within
the timestamp window or business-level conflicts. Apply updates and processing
status transactionally. Keep origin/change IDs to prevent loops. Allow retry when
storage fails; do not acknowledge and discard an event. Periodically reconcile.

No routes, database migrations or automatic model updates are registered. This keeps
the host application's event inbox and business decisions explicit. The same middleware
can be reused by any Laravel product; it does not call into a particular consumer.

## Tests and release

Once the PHP dependency is available (published or configured as a path repository):

```sh
composer install
composer test
composer validate --strict
```

This directory can become `vulcaire-ltd/staffapp-laravel`. Publish/tag the PHP client
first so this package's `^0.1` dependency resolves. The Composer licence is temporarily
`proprietary`; select the distribution licence before release. CI expects the PHP
package's `0.1` release to be available. Local workspace tests need no published packages.
