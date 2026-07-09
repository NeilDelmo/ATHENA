<?php

// Bridge for local servers still pointed at the repository root.
// Prefer using src/public as the web server document root outside local dev.
require __DIR__.'/src/public/index.php';
