<?php

return [
    'web_harvest' => [
        'enabled' => (bool) env('LITERATURE_WEB_HARVEST_ENABLED', true),
        'queue' => 'literature-harvest',
        'minimum_delay' => 5,
        'connect_timeout' => 4,
        'timeout' => 12,
        'maximum_bytes' => 4 * 1024 * 1024,
        'maximum_sitemaps' => 3,
        'maximum_urls' => 20000,
        'refresh_days' => 30,
        'repositories' => [
            'seafdec' => [
                'name' => 'SEAFDEC/AQD Repository',
                'origin' => 'https://repository.seafdec.org.ph',
                'sitemap' => 'https://repository.seafdec.org.ph/sitemap',
                'sitemap_pattern' => '~^/sitemap(?:\\?map=\\d+)?$~D',
                'item_pattern' => '~^/handle/10862/\\d+$~D',
            ],
        ],
    ],
    'full_text' => [
        'maximum_bytes' => (int) env('LITERATURE_FULL_TEXT_MAX_BYTES', 8 * 1024 * 1024),
        'maximum_characters' => (int) env('LITERATURE_FULL_TEXT_MAX_CHARACTERS', 30000),
        'maximum_pdf_pages' => (int) env('LITERATURE_FULL_TEXT_MAX_PDF_PAGES', 40),
        'connect_timeout' => (int) env('LITERATURE_FULL_TEXT_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('LITERATURE_FULL_TEXT_TIMEOUT', 18),
        'maximum_redirects' => (int) env('LITERATURE_FULL_TEXT_MAX_REDIRECTS', 3),
        'pdftotext_binary' => env('PDFTOTEXT_BINARY', 'pdftotext'),
        'temporary_directory' => env(
            'LITERATURE_FULL_TEXT_TEMP_PATH',
            storage_path('app/private/literature-full-text'),
        ),
    ],
];
