<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Draft checkpoint retention
    |--------------------------------------------------------------------------
    |
    | The working draft is saved on every autosave. These values only control
    | retention of automatic draft checkpoints independently of autosave.
    |
    */

    'checkpoint_interval_minutes' => 30,

    // Keep the newest recovery points plus the oldest one as a useful baseline.
    'automatic_checkpoint_limit' => 24,
];
