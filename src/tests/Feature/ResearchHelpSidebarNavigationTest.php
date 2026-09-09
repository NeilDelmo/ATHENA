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
        ->assertSee("sidebarOpen ? 'w-[280px]", false)
        ->assertSee("'sm:pl-[280px]': sidebarOpen", false)
        ->assertSee('pl-[76px]', false)
        ->assertSee('wire:navigate', false)
        ->assertSee('window.livewireScriptConfig', false)
        ->assertSee('data-research-help-menu', false)
        ->assertSee('aria-controls="research-help-feature-links"', false)
        ->assertSeeInOrder(['Research Help Facility', 'Literature Search and Source Organizer'])
        ->assertDontSee('AI Research Assistant')
        ->assertDontSee('href="'.$researchHelpUrl.'#ai-research-assistant"', false)
        ->assertSee('href="'.$researchHelpUrl.'#rrl-finder"', false)
        ->assertSee('href="'.$researchHelpUrl.'#turnitin"', false)
        ->assertDontSee('href="'.$researchHelpUrl.'#conference-finder"', false)
        ->assertSee('id="rrl-finder"', false)
        ->assertSee('id="turnitin"', false)
        ->assertSee('data-turnitin-resource', false)
        ->assertSee('Request a similarity check')
        ->assertSee('Sign in to Turnitin')
        ->assertSee('Read your report')
        ->assertDontSee('id="conference-finder"', false);
});

test('faculty researcher sidebar includes turnitin and conference discovery', function () {
    Role::firstOrCreate(['name' => 'faculty_researcher']);
    $researcher = User::factory()->create();
    $researcher->assignRole('faculty_researcher');

    $researchHelpUrl = route('research-support.index');

    $this->actingAs($researcher)
        ->get($researchHelpUrl)
        ->assertOk()
        ->assertSeeInOrder(['Research Help Facility', 'Literature Search and Source Organizer', 'Turnitin', 'Conference Finder'])
        ->assertSee('href="'.$researchHelpUrl.'#turnitin"', false)
        ->assertSee('href="'.$researchHelpUrl.'#conference-finder"', false)
        ->assertSee('id="turnitin"', false)
        ->assertSee('id="conference-finder"', false);
});
