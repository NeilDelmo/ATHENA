<?php

use App\Support\DetailedProposalRules;
use Tests\TestCase;

uses(TestCase::class);

test('a missing structured method heading produces only the heading requirement', function () {
    $errors = DetailedProposalRules::completionErrors([
        'methodology' => ['specific_methods' => ''],
        'specific_method_objectives' => [['heading' => '', 'methods' => [['description' => 'Interview residents.']]]],
    ]);

    expect($errors['specific_method_objectives.0.heading'])->toBe(['Enter a heading for method group A.'])
        ->and($errors)->not->toHaveKey('methodology.specific_methods');
});

test('the generated specific methods text retains its validations when there is no duplicate', function () {
    $completeGroup = [['heading' => 'Data collection', 'methods' => [['description' => 'Interview residents.']]]];
    expect(DetailedProposalRules::completionErrors(['methodology' => ['specific_methods' => '']]))
        ->toHaveKey('methodology.specific_methods');
    expect(DetailedProposalRules::completionErrors([
        'methodology' => ['specific_methods' => 'Interview residents.'],
    ]))->not->toHaveKey('methodology.specific_methods');
    expect(DetailedProposalRules::completionErrors([
        'methodology' => ['specific_methods' => ''],
        'specific_method_objectives' => $completeGroup,
    ]))->toHaveKey('methodology.specific_methods');
    expect(DetailedProposalRules::completionErrors([
        'methodology' => ['specific_methods' => str_repeat('a', config('detailed_proposal.maximum_narrative_length') + 1)],
        'specific_method_objectives' => [['heading' => '', 'methods' => [['description' => 'Interview residents.']]]],
    ]))->toHaveKeys(['methodology.specific_methods', 'specific_method_objectives.0.heading']);
});
