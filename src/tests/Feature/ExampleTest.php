<?php

it('presents ATHENA as the institutional research system for its authorized users', function () {
    $response = $this->get('/');

    $response
        ->assertSuccessful()
        ->assertSee('landing-visual--people', false)
        ->assertSee('Research Project Management')
        ->assertSee('Batangas State University - TNEU')
        ->assertSeeInOrder(['VCRDES', 'Research Head', 'Research Office', 'Faculty Researcher', 'Faculty'])
        ->assertSee('Sign in with your institutional account.')
        ->assertSee('Access is limited to authorized BatStateU ARASOF–Nasugbu users.')
        ->assertSee('Research roles and responsibilities.')
        ->assertSee('Role-based access')
        ->assertDontSee('Working together')
        ->assertDontSee('community')
        ->assertSee('Research management functions.')
        ->assertDontSee('Research moves forward here.')
        ->assertDontSee('Less friction. More progress.')
        ->assertDontSee('Ready when you are')
        ->assertDontSee('one sign-in away')
        ->assertDontSee('Welcome to ATHENA')
        ->assertDontSee('Explore ATHENA')
        ->assertDontSee('Research Management Portal');

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    expect((new DOMXPath($document))->query('//*[contains(concat(" ", normalize-space(@class), " "), " landing-status-pill ")]')->length)->toBe(0);
});

it('resolves the campus hero image under the configured asset base path', function () {
    $this->app['url']->useAssetOrigin('http://localhost/athena-app');

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('src="http://localhost/athena-app/images/bsu_front.png"', false);

    expect(is_file(public_path('images/bsu_front.png')))->toBeTrue();
});
