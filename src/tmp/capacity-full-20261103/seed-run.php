<?php

use Illuminate\Contracts\Console\Kernel;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require __DIR__.'/seed-web.php';
$context = capacitySeedWeb();
$fixtureDirectory = storage_path('app/private/capacity-fixtures');
$manifest = json_decode(file_get_contents($fixtureDirectory.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$context['fixture_directory'] = $fixtureDirectory;
$context['fixtures'] = $manifest;
$context['image_paths'] = array_map(fn (string $path): string => 'capacity-fixtures/'.$path, $manifest['image_paths']);
file_put_contents(storage_path('app/private/capacity-context.json'), json_encode($context, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['ok' => true, 'users' => $context['users'], 'projects' => $context['project_count'], 'draft_id' => $context['draft_id']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
