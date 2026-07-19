<?php

return [
    'libreoffice_binary' => env('LIBREOFFICE_BINARY', 'soffice'),
    'timeout_seconds' => (int) env('PDF_CONVERSION_TIMEOUT', 120),
];
