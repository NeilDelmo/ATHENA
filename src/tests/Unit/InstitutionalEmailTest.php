<?php

use App\Support\InstitutionalEmail;

test('it accepts the configured institutional email domain', function () {
    expect(InstitutionalEmail::isAllowed(
        'Faculty@G.BATSTATE-U.EDU.PH',
        ['g.batstate-u.edu.ph'],
    ))->toBeTrue();
});

test('it rejects personal malformed and lookalike email domains', function (?string $email) {
    expect(InstitutionalEmail::isAllowed($email, ['g.batstate-u.edu.ph']))->toBeFalse();
})->with([
    'personal Google account' => 'faculty@gmail.com',
    'lookalike suffix' => 'faculty@g.batstate-u.edu.ph.example.com',
    'lookalike prefix' => 'faculty@fake-g.batstate-u.edu.ph',
    'malformed email' => 'not-an-email',
    'missing email' => null,
]);
