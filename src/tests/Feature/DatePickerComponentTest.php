<?php

use Illuminate\Support\Facades\Blade;
use Symfony\Component\Process\Process;

test('the shared date picker renders an accessible direct year calendar', function () {
    $html = Blade::render(<<<'BLADE'
        <x-date-picker
            id="planned_start"
            name="planned_start"
            value="2026-08-01"
            min="2026-01-01"
            max="2027-12-31"
            required
        />
    BLADE);

    expect($html)
        ->toContain('x-data="datePicker({')
        ->toContain('name="planned_start"')
        ->toContain('id="planned_start"')
        ->toContain('role="combobox"')
        ->toContain('aria-haspopup="dialog"')
        ->toContain('aria-label="Choose a date"')
        ->toContain('Choose a month or type a year to jump directly.')
        ->toContain('<option value="0">January</option>')
        ->toContain('<option value="6">July</option>')
        ->toContain('<option value="11">December</option>')
        ->toContain('required');
});

test('project duration adds calendar months without overflowing shorter months', function () {
    $script = <<<'JS'
        import { addCalendarMonths } from './resources/js/proposal-draft-dates.js';

        console.log(JSON.stringify([
            addCalendarMonths('2026-07-21', 3),
            addCalendarMonths('2026-01-31', 1),
            addCalendarMonths('2028-01-31', 1),
        ]));
        JS;
    $process = new Process(['node', '--input-type=module', '--eval', $script], base_path());
    $process->mustRun();

    expect(json_decode(trim($process->getOutput()), true))->toBe([
        '2026-10-21',
        '2026-02-28',
        '2028-02-29',
    ]);
});

test('the shared date picker supports dynamic attachment c field bindings', function () {
    $html = Blade::render(<<<'BLADE'
        <x-date-picker
            id-expression="`cv-${person.id}-birthday`"
            name-expression="`people[${personIndex}][birthday]`"
            model="person.birthday"
        />
    BLADE);

    expect($html)
        ->toContain('x-modelable="value"')
        ->toContain('x-model="person.birthday"')
        ->toContain('x-bind:id="`cv-${person.id}-birthday`"')
        ->toContain('x-bind:name="`people[${personIndex}][birthday]`"');
});
