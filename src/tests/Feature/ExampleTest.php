<?php

it('presents the redesigned ATHENA landing page', function () {
    $response = $this->get('/');

    $response
        ->assertSuccessful()
        ->assertSee('landing-visual--people', false)
        ->assertSee('Research moves forward here.')
        ->assertSee('Batangas State University - TNEU')
        ->assertSeeInOrder(['VCRDES', 'Research Head', 'Research Office', 'Faculty Researcher', 'Faculty'])
        ->assertSee('Continue with Spartan email')
        ->assertSee('All steps connected')
        ->assertSee('Working together')
        ->assertSee('Everything important, within reach.')
        ->assertDontSee('Welcome to ATHENA')
        ->assertDontSee('Explore ATHENA')
        ->assertDontSee('Research Management Portal');
});

it('resolves the campus hero image under the configured asset base path', function () {
    $this->app['url']->useAssetOrigin('http://localhost/athena-app');

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('src="http://localhost/athena-app/images/bsu_front.png"', false);

    expect(is_file(public_path('images/bsu_front.png')))->toBeTrue();
});
