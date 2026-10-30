<?php

use App\Support\DetailedProposalData;
use App\Support\DetailedProposalRules;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

test('responsibility allocation remembers fixed and automatic percentages through normalization', function () {
    $rows = [
        ['name' => 'Project leader', 'percentage' => 50, 'percentage_fixed' => true, 'duties' => 'Lead the research.'],
        ['name' => 'Project staff', 'percentage' => 50, 'percentage_fixed' => false, 'duties' => 'Collect data.'],
        ['name' => '', 'percentage' => '', 'percentage_fixed' => '0', 'duties' => ''],
    ];
    $data = DetailedProposalData::fromValidated(['responsibilities' => $rows]);
    expect($data['responsibilities'])->toHaveCount(2)
        ->and($data['responsibilities'][0]['percentage_fixed'])->toBe('1')
        ->and($data['responsibilities'][1]['percentage_fixed'])->toBe('0')
        ->and(DetailedProposalData::fromValidated($data)['responsibilities'])->toBe($data['responsibilities']);
});

test('legacy responsibility percentages are preserved as manually fixed', function () {
    $data = DetailedProposalData::fromValidated(['responsibilities' => [
        ['name' => 'Project leader', 'percentage' => 60, 'duties' => 'Lead the research.'],
        ['name' => 'Project staff', 'percentage' => 40, 'duties' => 'Collect data.'],
    ]]);
    expect(array_column($data['responsibilities'], 'percentage_fixed'))->toBe(['1', '1']);
});

test('complete responsibility shares must total exactly one hundred percent', function (array $shares, bool $valid) {
    $rows = array_map(fn (int $percentage): array => ['name' => 'Researcher', 'percentage' => $percentage, 'duties' => 'Conduct the research.'], $shares);
    $errors = DetailedProposalRules::completionErrors(['responsibilities' => $rows]);
    expect(array_key_exists('responsibilities', $errors))->toBe(! $valid);
})->with([
    [[100], true],
    [[50, 25, 25], true],
    [[34, 33, 33], true],
    [[50, 20, 20], false],
    [[80, 30], false],
]);

test('unfinished responsibility allocations can still be saved as a draft', function () {
    $validator = Validator::make(['responsibilities' => [
        ['name' => 'Project leader', 'percentage' => 50, 'percentage_fixed' => '1', 'duties' => 'Lead the research.'],
    ]], DetailedProposalRules::rules(allowDraft: true));
    foreach (DetailedProposalRules::afterCallbacks(allowDraft: true) as $callback) {
        $validator->after($callback);
    }
    expect($validator->errors()->has('responsibilities'))->toBeFalse();
});

test('responsibility fixed flags reject invalid input', function () {
    $rules = DetailedProposalRules::rules(allowDraft: true);
    expect($rules)->toHaveKey('responsibilities.*.percentage_fixed');
    $validator = Validator::make(['responsibilities' => [['percentage_fixed' => 'not-a-boolean']]], [
        'responsibilities.*.percentage_fixed' => $rules['responsibilities.*.percentage_fixed'],
    ]);
    expect($validator->errors()->has('responsibilities.0.percentage_fixed'))->toBeTrue();
});
