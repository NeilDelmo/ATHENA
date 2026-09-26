<?php

it('presents the redesigned ATHENA landing page', function () {
    $response = $this->get('/');

    $response
        ->assertSuccessful()
        ->assertSee('images/bsu_front.png', false)
        ->assertSee('Research moves forward here.')
        ->assertSee('Continue with Spartan email')
        ->assertDontSee('Welcome to ATHENA')
        ->assertDontSee('Explore ATHENA')
        ->assertDontSee('Research Management Portal');
});
