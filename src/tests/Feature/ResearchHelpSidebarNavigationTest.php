<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('faculty sidebar exposes the rrl finder without conference discovery', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $researchHelpUrl = route('research-support.index');

    $this->actingAs($faculty)
        ->get($researchHelpUrl)
        ->assertOk()
        ->assertSee("\$store.sidebar.open ? '!w-[280px]", false)
        ->assertSee("\$store.sidebar.open ? 'sm:!pl-[280px]'", false)
        ->assertSee('pl-[76px] sm:pl-[280px]', false)
        ->assertSee('wire:navigate', false)
        ->assertSee('wire:navigate:scroll', false)
        ->assertSee('wire:current', false)
        ->assertSee('<aside', false)
        ->assertSee('x-data', false)
        ->assertSee('data-app-content-shell', false)
        ->assertDontSee('transition-[padding,background-color]', false)
        ->assertSee('x-on:livewire:navigated.window', false)
        ->assertSee('window.livewireScriptConfig', false)
        ->assertSee('data-research-help-menu', false)
        ->assertDontSee('aria-controls="research-help-feature-links"', false)
        ->assertDontSee('researchHelpOpen', false)
        ->assertSeeInOrder(['Resources', 'Saved literature', 'Literature search', 'Turnitin'])
        ->assertDontSee('AI Research Assistant')
        ->assertDontSee('href="'.$researchHelpUrl.'#ai-research-assistant"', false)
        ->assertSee('href="'.$researchHelpUrl.'#rrl-finder"', false)
        ->assertSee('href="'.$researchHelpUrl.'#turnitin"', false)
        ->assertDontSee('href="'.$researchHelpUrl.'#journal-finder"', false)
        ->assertSee('id="rrl-finder"', false)
        ->assertSee('id="turnitin"', false)
        ->assertSee('data-turnitin-resource', false)
        ->assertSee('Open Turnitin')
        ->assertDontSee('Request a similarity check')
        ->assertSee('Read your report')
        ->assertDontSee('id="journal-finder"', false);

    expect(file_get_contents(resource_path('js/app.js')))
        ->toContain("Alpine.store('sidebar'")
        ->toContain('initializeSidebarAttentionLinks()')
        ->toContain('Livewire.start();')
        ->not->toContain("typeof window.livewireScriptConfig !== 'undefined'")
        ->toContain("document.addEventListener('livewire:navigated'");
});

test('faculty researcher sidebar includes turnitin and journal discovery', function () {
    Role::firstOrCreate(['name' => 'faculty_researcher']);
    $researcher = User::factory()->create();
    $researcher->assignRole('faculty_researcher');

    $researchHelpUrl = route('research-support.index');

    $this->actingAs($researcher)
        ->get($researchHelpUrl)
        ->assertOk()
        ->assertSeeInOrder(['Resources', 'Saved literature', 'Literature search', 'Turnitin', 'Journal Finder'])
        ->assertSee('href="'.$researchHelpUrl.'#turnitin"', false)
        ->assertSee('href="'.$researchHelpUrl.'#journal-finder"', false)
        ->assertSee('id="turnitin"', false)
        ->assertSee('id="journal-finder"', false);
});
