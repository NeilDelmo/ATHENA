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
        </main>
    </body>
</html>
