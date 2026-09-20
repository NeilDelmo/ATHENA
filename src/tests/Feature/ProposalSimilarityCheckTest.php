<?php

use Illuminate\Support\Facades\Route;

test('the standalone similarity check workflow is not exposed', function () {
    $topicView = file_get_contents(resource_path('views/topics/show.blade.php'));
    $researchSupportView = file_get_contents(resource_path('views/faculty/research_support/index.blade.php'));

    expect(Route::has('similarity-checks.index'))->toBeFalse()
        ->and(Route::has('similarity-checks.store'))->toBeFalse()
        ->and(Route::has('similarity-checks.update'))->toBeFalse()
        ->and(Route::has('similarity-checks.download'))->toBeFalse()
        ->and(resource_path('views/faculty/research_support/similarity-checks.blade.php'))->not->toBeFile()
        ->and($topicView)->not->toContain('Open Turnitin resources', 'similarity-checks')
        ->and($researchSupportView)->not->toContain('Open Turnitin resources', 'similarity-checks');

    $this->get('/research-support/similarity-checks')->assertNotFound();
});
