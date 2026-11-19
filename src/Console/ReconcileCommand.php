<?php

namespace Vulcaire\StaffApp\Laravel\Console;

use Illuminate\Console\Command;
use Vulcaire\StaffApp\Laravel\Contracts\ReconcileSource;
use Vulcaire\StaffApp\Laravel\MirrorRecord;
use Vulcaire\StaffApp\Laravel\Sync;

class ReconcileCommand extends Command
{
    protected $signature = 'staffapp:reconcile {source?} {--apply : Link unambiguous existing records. Does not create or overwrite.} {--push : Link matches, then upsert linked and new records. Skips ambiguous and conflicting rows.} {--dry-run : Report only. This is the default.}';

    protected $description = 'Compare a host source with AssignPoint and optionally link or push records.';

    public function handle(Sync $sync): int
    {
        $class = $this->argument('source') ?: config('staffapp.reconcile_source');
        if (! is_string($class) || $class === '' || ! is_subclass_of($class, ReconcileSource::class)) {
            $this->error('Pass a class that implements '.ReconcileSource::class.', or set staffapp.reconcile_source.');

            return self::FAILURE;
        }

        $dryRun = ! $this->option('apply') && ! $this->option('push');
        $records = [];
        foreach (app($class)->records() as $record) {
            if (! $record instanceof MirrorRecord) {
                $this->error('The source must yield MirrorRecord instances.');

                return self::FAILURE;
            }
            $records[] = $record->identity();
        }

        $report = $sync->reconcile($records, true);
        $rows = $report['data']['results'] ?? [];
        $this->table(['Type', 'External ID', 'Outcome', 'Match', 'AssignPoint ID', 'Message'], array_map(fn (array $row): array => [
            $row['type'] ?? '',
            $row['external_id'] ?? '',
            $row['outcome'] ?? '',
            $row['match'] ?? '',
            $row['id'] ?? '',
            $row['message'] ?? '',
        ], $rows));

        if ($dryRun) {
            $this->info('Dry run only. Nothing was written. Pass --apply to link unambiguous matches, or --push to link and then upsert.');

            return self::SUCCESS;
        }

        $sync->reconcile($records, false);
        if ($this->option('push')) {
            foreach (app($class)->records() as $record) {
                $outcome = collect($rows)->first(fn (array $row): bool => ($row['external_id'] ?? null) === $record->externalId && ($row['type'] ?? null) === $record->kind);
                if (in_array($outcome['outcome'] ?? '', ['ambiguous', 'conflict'], true)) {
                    $this->warn('Skipped '.$record->kind.' '.$record->externalId.' ('.$outcome['outcome'].').');

                    continue;
                }
                $sync->later($record->kind, $record->externalId, $record->sourceVersion, $record->attributes, $record->remove);
            }
            $this->info('Linked unambiguous matches and queued upserts for matched and new records.');
        } else {
            $this->info('Linked unambiguous matches. No other fields were changed.');
        }

        return self::SUCCESS;
    }
}
