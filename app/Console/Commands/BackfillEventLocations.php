<?php

namespace App\Console\Commands;

use App\Support\CityResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillEventLocations extends Command
{
    protected $signature = 'events:backfill-locations
                            {--chunk=5000 : Rows to process per batch}
                            {--force : Re-resolve rows that already have a city}';

    protected $description = 'Resolve each event\'s latitude/longitude into a city, country and timezone';

    public function handle(CityResolver $resolver): int
    {
        $chunk = (int) $this->option('chunk');
        $force = (bool) $this->option('force');

        $base = DB::table('events')->select('id', 'latitude', 'longitude');
        if (! $force) {
            $base->whereNull('city');
        }

        $total = (clone $base)->count();
        if ($total === 0) {
            $this->info('Nothing to backfill.');

            return self::SUCCESS;
        }

        $this->info("Backfilling locations for {$total} events...");
        $bar = $this->output->createProgressBar($total);
        $done = 0;
        $lastId = '';

        // Keyset pagination on the uuid primary key — stable and index-friendly
        // even while we update the rows we're iterating over.
        while (true) {
            $rows = (clone $base)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($chunk)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $cases = ['city' => [], 'country' => [], 'timezone' => []];
            $ids = [];

            foreach ($rows as $row) {
                $resolved = $resolver->resolve((float) $row->latitude, (float) $row->longitude);

                $cases['city'][$row->id] = $resolved['city'];
                $cases['country'][$row->id] = $resolved['country'];
                $cases['timezone'][$row->id] = $resolved['timezone'];
                $ids[] = $row->id;
                $lastId = $row->id;
            }

            // One UPDATE per chunk using CASE expressions, instead of N updates.
            DB::transaction(function () use ($cases, $ids) {
                foreach (['city', 'country', 'timezone'] as $column) {
                    $sql = "UPDATE events SET {$column} = CASE id";
                    $bindings = [];
                    foreach ($cases[$column] as $id => $value) {
                        $sql .= ' WHEN ? THEN ?';
                        $bindings[] = $id;
                        $bindings[] = $value;
                    }
                    $sql .= ' END WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')';
                    DB::update($sql, array_merge($bindings, $ids));
                }
            });

            $done += $rows->count();
            $bar->advance($rows->count());
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Resolved {$done} events.");

        return self::SUCCESS;
    }
}
