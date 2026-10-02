<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Initial Screening Form Preview</title>
        @vite('resources/css/initial-screening-form-print.css')
    </head>
    <body class="initial-screening-preview-page">
        <main class="initial-screening-sheet" aria-label="BatStateU Initial Screening Form">
            <img src="{{ asset('images/initial-screening-form-preview.png') }}" alt="" class="initial-screening-source" aria-hidden="true">
            <span class="initial-screening-project-title">{{ $screeningForm['project_title'] }}</span>
            <span class="initial-screening-project-leader">{{ $screeningForm['project_leader'] }}</span>
            <span class="initial-screening-order-checkmark initial-screening-order-{{ $screeningForm['order_of_submission'] }}" data-screening-order="{{ $screeningForm['order_of_submission'] }}" aria-label="Selected order of submission">×</span>
            @if (in_array($screeningForm['level_of_call'] ?? null, ['central_agency', 'constituent_campus'], true))
                <span class="initial-screening-level-checkmark initial-screening-level-{{ $screeningForm['level_of_call'] }}" data-screening-level="{{ $screeningForm['level_of_call'] }}" aria-label="Selected level of call">×</span>
            @endif
        </main>
    </body>
</html>
