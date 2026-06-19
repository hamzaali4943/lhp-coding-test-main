<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded Event Rows
    |--------------------------------------------------------------------------
    |
    | Number of events created by the EventSeeder. Defaults to 1,250,000
    | (≈2.5 GB). Override with SEED_ROWS, e.g. SEED_ROWS=50000 php artisan db:seed
    |
    */

    'event_rows' => (int) env('SEED_ROWS', 1_250_000),

];
