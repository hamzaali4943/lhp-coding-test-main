<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Covering index for the map's "events per city" aggregate.
     *
     * The map runs `GROUP BY city` over a date range, optionally filtered by
     * status/type. With city leading, SQLite streams the aggregate in city
     * order (no temp sort); with created_time/status/type all present in the
     * index, the whole query is index-only and never touches the 2.5 GB of row
     * data (notably the large `payload` column). It also speeds up the grid's
     * "events in a city, ordered by date" query.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->index(['city', 'created_time', 'status', 'type'], 'events_map_aggregate_index');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_map_aggregate_index');
        });
    }
};
