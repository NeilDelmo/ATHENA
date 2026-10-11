<?php

use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class);

test('HTML errors use the shared ATHENA page and keep their status and headers', function (int $status, string $heading) {
    config(['app.debug' => false]);
    $this->withoutVite();

    $response = app(ExceptionHandler::class)->render(
        Request::create('/unavailable-page'),
        new HttpException($status, 'Private exception details', headers: ['Retry-After' => '60']),
    );

    TestResponse::fromBaseResponse($response)
        ->assertStatus($status)
        ->assertHeader('Retry-After', '60')
        ->assertSee("Error {$status}")
        ->assertSee($heading)
        ->assertSee('ATHENA')
        ->assertDontSee('Private exception details');
})->with([
    'sign in required' => [401, 'Sign in to continue.'],
    'access denied' => [403, "This page isn't for your account."],
    'page not found' => [404, 'We could not find that page.'],
    'unsupported action' => [405, 'This action is not available here.'],
    'expired session' => [419, 'Your session has expired.'],
    'too many requests' => [429, 'Please slow down for a moment.'],
    'server error' => [500, 'Something went wrong on our side.'],
    'maintenance' => [503, 'ATHENA is taking a short break.'],
    'other client errors' => [410, 'Your request could not be completed.'],
    'other server errors' => [502, 'Something went wrong on our side.'],
]);

test('access denied explains workspace access and provides an authenticated recovery link', function () {
    $this->withoutVite();
    $this->actingAs(User::factory()->make());

    $this->view('errors.403')
        ->assertSee('Your account or current workspace does not have access to this page.')
        ->assertSee('Go to my dashboard')
        ->assertSee(route('dashboard'));
});

test('unauthorized visitors have a direct sign in link', function () {
    $this->withoutVite();

    $this->view('errors.401')
        ->assertSee('Sign in to continue.')
        ->assertSee(route('login'));
});
