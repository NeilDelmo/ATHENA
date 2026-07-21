<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('faculty sidebar expands into the three working research help features', function () {
    Role::firstOrCreate(['name' => 'faculty']);
    $faculty = User::factory()->create();
    $faculty->assignRole('faculty');

    $researchHelpUrl = route('research-support.index');

    $this->actingAs($faculty)
        ->get($researchHelpUrl)
        ->assertOk()
        ->assertSee('data-research-help-menu', false)
        ->assertSee('aria-controls="research-help-feature-links"', false)
        ->assertSeeInOrder([
            'Research Help Facility',
            'AI Research Assistant',
            'RRL Finder',
            'Conference Finder',
        ])
        ->assertSee('href="'.$researchHelpUrl.'#ai-research-assistant"', false)
        ->assertSee('href="'.$researchHelpUrl.'#rrl-finder"', false)
        ->assertSee('href="'.$researchHelpUrl.'#conference-finder"', false)
        ->assertSee('id="ai-research-assistant"', false)
        ->assertSee('id="rrl-finder"', false)
        ->assertSee('id="conference-finder"', false);
});
