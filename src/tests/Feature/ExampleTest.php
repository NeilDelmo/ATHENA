<?php

it('presents the redesigned ATHENA landing page', function () {
    $response = $this->get('/');

    $response
        ->assertSuccessful()
        ->assertSee('landing-visual--people', false)
        ->assertSee('Research moves forward here.')
        ->assertSee('Continue with Spartan email')
        ->assertSee('All steps connected')
        ->assertSee('Working together')
        ->assertSee('Everything important, within reach.')
        ->assertDontSee('Welcome to ATHENA')
        ->assertDontSee('Explore ATHENA')
        ->assertDontSee('Research Management Portal');
});
