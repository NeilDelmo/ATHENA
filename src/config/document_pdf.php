<?php

return [
    'libreoffice_binary' => env(
        'LIBREOFFICE_BINARY',
        PHP_OS_FAMILY === 'Windows' ? 'soffice.com' : 'soffice',
    ),
    'temporary_directory' => env(
        'PDF_CONVERSION_TEMP_PATH',
        storage_path('app/private/pdf-conversions'),
    ),
    'delegate_to_php_cli' => env(
        'PDF_CONVERSION_USE_PHP_CLI',
        PHP_OS_FAMILY === 'Windows' && PHP_SAPI !== 'cli',
    ),
    'php_cli_binary' => env(
        'PHP_CLI_BINARY',
        PHP_OS_FAMILY === 'Windows'
            ? dirname((string) php_ini_loaded_file()).DIRECTORY_SEPARATOR.'php.exe'
            : PHP_BINARY,
    ),
    'php_cli_temporary_directory' => env(
        'PHP_CLI_TEMP_PATH',
        storage_path('app/private/php-cli-temp'),
    ),
    'timeout_seconds' => (int) env('PDF_CONVERSION_TIMEOUT', 120),
];
