<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Recovery history
    |--------------------------------------------------------------------------
    |
    | The working draft is saved on every autosave. These values only control
    | the smaller set of automatic recovery points shown in Recovery history.
    |
    */

    'checkpoint_interval_minutes' => 30,

    // Keep the newest recovery points plus the oldest one as a useful baseline.
    'automatic_checkpoint_limit' => 24,
];
