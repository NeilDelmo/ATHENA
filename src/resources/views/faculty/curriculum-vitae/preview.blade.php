<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Attachment C - Curriculum Vitae Preview</title>
        @vite('resources/css/curriculum-vitae-print.css')
    </head>
    <body class="cv-preview-page">
        @foreach ($curriculumVitae['people'] as $person)
            <main class="cv-sheet" aria-label="BatStateU Attachment C Curriculum Vitae for {{ $person['first_name'] }} {{ $person['last_name'] }}">
                <p class="cv-form-code">Attachment C-BatStateU-FO-RES-02</p>
                <h1>CURRICULUM VITAE</h1>

                <table class="cv-table cv-name-table">
                    <colgroup>
                        <col class="cv-last-name-column">
                        <col class="cv-first-name-column">
                        <col class="cv-middle-name-column">
                    </colgroup>
                    <tbody>
                        <tr class="cv-section-heading"><th colspan="3">PERSONAL INFORMATION</th></tr>
                        <tr class="cv-name-labels"><th>Last Name</th><th>First Name</th><th>Middle Name</th></tr>
                        <tr class="cv-name-values"><td>{{ $person['last_name'] }}</td><td>{{ $person['first_name'] }}</td><td>{{ $person['middle_name'] }}</td></tr>
                    </tbody>
                </table>

                <table class="cv-table cv-personal-table">
                    <tbody>
                        <tr><td>Agency: {{ $person['agency'] }}</td><td colspan="2">Gender: {{ $person['gender'] === 'male' ? '☒ Male  ☐ Female' : ($person['gender'] === 'female' ? '☐ Male  ☒ Female' : '☐ Male  ☐ Female') }}</td><td>Birthday (mm/dd/yyyy): {{ $person['birthday'] }}</td></tr>
                        <tr class="cv-section-heading"><th colspan="4">RESIDENTIAL ADDRESS</th></tr>
                        <tr class="cv-name-labels"><th>Street</th><th>Barangay</th><th>Municipality</th><th>Province</th></tr>
                        <tr><td>{{ $person['street'] }}</td><td>{{ $person['barangay'] }}</td><td>{{ $person['municipality'] }}</td><td>{{ $person['province'] }}</td></tr>
                        <tr><td>Landline no.: {{ $person['landline'] }}</td><td colspan="2">Cellphone no.: (+63) {{ $person['cellphone'] }}</td><td>Email Address: {{ $person['email'] }}</td></tr>
                    </tbody>
                </table>

                @foreach (config('curriculum_vitae.sections') as $sectionKey => $section)
                    @if ($sectionKey === 'publications')
                        <div class="cv-table-break"></div>
                    @endif
                    <table class="cv-table cv-detail-table">
                        <thead>
                            <tr class="cv-section-heading"><th colspan="{{ count($section['fields']) }}">{{ strtoupper($section['label']) }}</th></tr>
                            <tr>
                                @foreach ($section['fields'] as $field)
                                    <th>{{ $field['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($person[$sectionKey] as $entry)
                                <tr>
                                    @foreach ($section['fields'] as $field)
                                        <td>{{ $entry[$field['key']] ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    @foreach ($section['fields'] as $field)
                                        <td>&nbsp;</td>
                                    @endforeach
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                @endforeach
            </main>
        @endforeach
    </body>
</html>
