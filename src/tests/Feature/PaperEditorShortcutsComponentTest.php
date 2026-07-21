<?php

use Illuminate\Support\Facades\Blade;

test('editor shortcuts are hidden in a contextual header dropdown opened by a keyboard button', function () {
    $html = Blade::render('<x-paper-editor-shortcuts />');

    expect($html)
        ->toContain('data-paper-shortcuts-trigger')
        ->toContain('x-on:click="open = ! open"')
        ->toContain('x-bind:aria-expanded="open"')
        ->toContain('aria-controls="paper-editor-shortcuts-dropdown"')
        ->toContain('>Shortcuts</span>')
        ->toContain('data-paper-shortcuts-dropdown')
        ->toContain('x-cloak')
        ->toContain('x-show="open"')
        ->toContain('x-on:click.outside="open = false"')
        ->toContain('x-on:keydown.escape.window=')
        ->toContain('border-gray-300')
        ->toContain('text-gray-700')
        ->toContain('dark:bg-slate-800')
        ->toContain('Editor shortcuts')
        ->toContain('Ctrl + S')
        ->toContain('Ctrl + Enter')
        ->toContain('Ctrl + Alt + R')
        ->toContain('Ctrl + Alt + X')
        ->not->toContain('data-paper-shortcuts-modal')
        ->not->toContain('role="dialog"');
});

test('shortcuts sit beside new proposal instead of in the global application header', function () {
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
    $workspace = file_get_contents(resource_path('views/faculty/proposal-drafts/index.blade.php'));
    [$globalHeader, $contextualHeader] = explode('@isset($header)', $layout, 2);
    $shortcutsPosition = strpos($workspace, '<x-paper-editor-shortcuts />');
    $newProposalPosition = strpos($workspace, "route('faculty.proposal-drafts.create')");

    expect($globalHeader)->not->toContain('<x-paper-editor-shortcuts />')
        ->and($contextualHeader)->toContain('<x-paper-editor-shortcuts />')
        ->toContain('sm:items-start')
        ->not->toContain('sm:items-center')
        ->and($workspace)->toContain('Proposal Package Workspace')
        ->and($shortcutsPosition)->toBeInt()->toBeLessThan($newProposalPosition);
});
