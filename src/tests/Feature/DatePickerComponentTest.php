<?php

use Illuminate\Support\Facades\Blade;

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
        ->toContain('required');
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
