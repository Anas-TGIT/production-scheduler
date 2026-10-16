<?php

return [
    /*
     * Setup/changeover delay applied between two adjacent orders of different product types.
     * Must be >= 0. The brief's 30 minutes is only the default.
     */
    'changeover_minutes' => (int) env('SCHEDULING_CHANGEOVER_MINUTES', 30),

    /*
     * Timezone used to interpret deadlines typed in forms and to display timestamps.
     * Storage and calculations always use UTC (config('app.timezone')).
     */
    'display_timezone' => env('SCHEDULING_DISPLAY_TIMEZONE', 'America/Toronto'),

    /*
     * Work budget of the optional changeover-aware local search: total order placements it may simulate
     * (one simulation of n orders costs n; each placement takes a few microseconds). 200,000 keeps the
     * worst case around 1–2 seconds whatever the number of orders. 0 disables the search (plain EDD).
     */
    'heuristic' => [
        'max_simulated_orders' => (int) env('SCHEDULING_HEURISTIC_MAX_SIMULATED_ORDERS', 200_000),
    ],
];
