<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Human-readable location resolved from latitude/longitude. Persisted
            // (rather than computed per request) so the listing and map can filter
            // and aggregate over 1.25M rows using indexes.
            $table->string('city')->nullable()->after('longitude');
            $table->string('country')->nullable()->after('city');
            $table->string('timezone')->nullable()->after('country');

            $table->index('city');
            $table->index('country');
            // created_time holds the unix start timestamp; the grid orders/filters on it.
            $table->index('created_time');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['city']);
            $table->dropIndex(['country']);
            $table->dropIndex(['created_time']);
            $table->dropColumn(['city', 'country', 'timezone']);
        });
    }
};
