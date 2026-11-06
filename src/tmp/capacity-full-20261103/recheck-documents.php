<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Process;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require_once __DIR__.'/workload-documents.php';
$directory = storage_path('app/private/capacity-output');
$results = [];
foreach (['terminal-images-tables' => ['REPORTFIGURE12END', 'T6R100C6END', 'BIBLIOGRAPHYEND'],
    'quarterly-evidence' => ['PRESENTATION100END', 'PUBLICATION100END', 'RESEARCH100END', 'QUARTERLYFIGURE12END']] as $case => $markers) {
    $pdf = $directory.'/'.$case.'.pdf';
    $info = Process::run(['/usr/bin/pdfinfo', $pdf]);
    capacityDocAssert($info->successful() && preg_match('/^Pages:\s+(\d+)/m', $info->output(), $matches) === 1, 'Cannot inspect actual PDF.');
    $pages = (int) $matches[1];
    $layout = file_get_contents($directory.'/'.$case.'.txt');
    $compact = preg_replace('/\s+/u', '', $layout);
    $raw = Process::run(['/usr/bin/pdftotext', '-raw', $pdf, '-']);
    capacityDocAssert($raw->successful(), 'Cannot read actual PDF.');
    $rawCompact = preg_replace('/\s+/u', '', $raw->output());
    $checks = [];
    foreach ($markers as $marker) {
        $method = str_contains($compact, $marker) ? ['method' => 'exact_layout_text']
            : (str_contains($rawCompact, $marker) ? ['method' => 'exact_reading_order_text']
                : capacityDocWrappedTableMarker($pdf, $layout, $marker, $pages));
        capacityDocAssert($method !== null, 'Actual PDF lost marker '.$marker);
        $checks[$marker] = $method;
    }
    $results[$case] = ['valid' => true, 'pages' => $pages, 'sha256' => hash_file('sha256', $pdf), 'checks' => $checks];
}
file_put_contents($directory.'/marker-recheck.json', json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
