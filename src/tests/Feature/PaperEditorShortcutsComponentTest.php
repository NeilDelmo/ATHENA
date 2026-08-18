<?php

test('proposal pages do not render redundant editor shortcuts', function () {
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
    $workspace = file_get_contents(resource_path('views/faculty/proposal-drafts/index.blade.php'));
    $scripts = file_get_contents(resource_path('js/app.js'));

    expect($layout)->not->toContain('paper-editor-shortcuts')
        ->and($workspace)->not->toContain('paper-editor-shortcuts')
        ->and($scripts)->not->toContain("key === 's'")
        ->not->toContain("key === 'enter'");
});
