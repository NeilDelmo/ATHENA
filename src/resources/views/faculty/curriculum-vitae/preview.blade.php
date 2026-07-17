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
                <div class="cv-form">
                    <header class="cv-title-block">
                        <p>Attachment C-BatStateU-FO-RES-02</p>
                        <h1>CURRICULUM VITAE</h1>
                        <p aria-hidden="true">&nbsp;</p>
                    </header>

                    <table class="cv-table cv-name-table">
                        <colgroup><col><col><col></colgroup>
                        <tbody>
                            <tr class="cv-section-heading"><th colspan="3">PERSONAL INFORMATION</th></tr>
                            <tr class="cv-label-row cv-borderless-columns"><th>Last Name</th><th>First Name</th><th>Middle Name</th></tr>
                            <tr class="cv-value-row cv-borderless-columns"><td>{{ $person['last_name'] }}</td><td>{{ $person['first_name'] }}</td><td>{{ $person['middle_name'] }}</td></tr>
                        </tbody>
                    </table>

                    <table class="cv-table cv-personal-details-table">
                        <colgroup><col><col><col></colgroup>
                        <tbody>
                            <tr class="cv-data-row"><td>Agency: {{ $person['agency'] }}</td><td>Gender: {{ $person['gender'] === 'male' ? '☒ Male  ☐ Female' : ($person['gender'] === 'female' ? '☐ Male  ☒ Female' : '☐ Male  ☐ Female') }}</td><td>Birthday (mm/dd/yyyy): {{ $person['birthday'] }}</td></tr>
                            <tr class="cv-data-row"><td colspan="3">&nbsp;</td></tr>
                        </tbody>
                    </table>

                    <table class="cv-table cv-address-table">
                        <colgroup><col><col><col><col></colgroup>
                        <tbody>
                            <tr class="cv-label-row cv-borderless-columns"><th>Street</th><th>Barangay</th><th>Municipality</th><th>Province</th></tr>
                            <tr class="cv-value-row cv-borderless-columns"><td>{{ $person['street'] }}</td><td>{{ $person['barangay'] }}</td><td>{{ $person['municipality'] }}</td><td>{{ $person['province'] }}</td></tr>
                        </tbody>
                    </table>

                    <table class="cv-table cv-contact-table">
                        <colgroup><col><col><col></colgroup>
                        <tbody>
                            <tr class="cv-data-row"><td>Landline no.: {{ $person['landline'] }}</td><td>Cellphone no.: (+63) {{ $person['cellphone'] }}</td><td>Email Address: {{ $person['email'] }}</td></tr>
                        </tbody>
                    </table>

                    <table class="cv-table cv-detail-table cv-academic-table">
                        <colgroup><col><col><col><col><col><col><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading"><th colspan="8">ACADEMIC BACKGROUND</th></tr>
                            <tr class="cv-detail-heading"><th>Degree Earned<span class="cv-header-hint">(from highest to lowest)</span></th><th>Major Field</th><th>Sector</th><th>Learning Institution</th><th>Status<span class="cv-header-hint">(graduated, ongoing, dropped, terminated)</span></th><th colspan="2">Year Taken<span class="cv-header-hint">(yyyy)</span></th><th>Thesis</th></tr>
                            <tr class="cv-detail-heading cv-detail-subheading"><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>Start</th><th>End</th><th>&nbsp;</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($person['academic_background'] as $entry)
                                <tr class="cv-data-row"><td>{{ $entry['degree'] ?? '' }}</td><td>{{ $entry['major_field'] ?? '' }}</td><td>{{ $entry['sector'] ?? '' }}</td><td>{{ $entry['learning_institution'] ?? '' }}</td><td>{{ $entry['status'] ?? '' }}</td><td>{{ $entry['year_start'] ?? '' }}</td><td>{{ $entry['year_end'] ?? '' }}</td><td>{{ $entry['thesis'] ?? '' }}</td></tr>
                            @empty
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    <table class="cv-table cv-detail-table cv-scholarship-table">
                        <colgroup><col><col><col><col><col><col><col><col><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading cv-section-heading-light"><th colspan="10">SCHOLARSHIP</th></tr>
                            <tr class="cv-detail-heading"><th>Sponsor</th><th>Primary Sponsor<span class="cv-header-hint">(Yes or No)</span></th><th colspan="2">Scholarship Period<span class="cv-header-hint">(yyyy-mm-dd)</span></th><th colspan="2">Extension Period<span class="cv-header-hint">(yyyy-mm-dd)</span></th><th colspan="4">Scholarship Grants</th></tr>
                            <tr class="cv-detail-heading cv-detail-subheading"><th>&nbsp;</th><th>&nbsp;</th><th>Start</th><th>End</th><th>Start</th><th>End</th><th>Item Expenses</th><th>Amount Approved</th><th>Amount Released</th><th>Date Released<span class="cv-header-hint">(yyyy-mm-dd)</span></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($person['scholarships'] as $entry)
                                <tr class="cv-data-row"><td>{{ $entry['sponsor'] ?? '' }}</td><td>{{ $entry['primary_sponsor'] ?? '' }}</td><td>{{ $entry['period_start'] ?? '' }}</td><td>{{ $entry['period_end'] ?? '' }}</td><td>{{ $entry['extension_start'] ?? '' }}</td><td>{{ $entry['extension_end'] ?? '' }}</td><td>{{ $entry['item_expenses'] ?? '' }}</td><td>{{ $entry['amount_approved'] ?? '' }}</td><td>{{ $entry['amount_released'] ?? '' }}</td><td>{{ $entry['date_released'] ?? '' }}</td></tr>
                            @empty
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    <table class="cv-table cv-detail-table cv-employment-table">
                        <colgroup><col><col><col><col><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading"><th colspan="6">EMPLOYMENT</th></tr>
                            <tr class="cv-detail-heading"><th>Agency</th><th>Plantilla Position</th><th>Status of Appointment<span class="cv-header-hint">(permanent, temporary, contractual, casual, emergency)</span></th><th colspan="2">Date of Appointment<span class="cv-header-hint">(yyyy-mm-dd)</span></th><th>Monthly Salary</th></tr>
                            <tr class="cv-detail-heading cv-detail-subheading"><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>Start</th><th>End</th><th>&nbsp;</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($person['employment'] as $entry)
                                <tr class="cv-data-row"><td>{{ $entry['agency'] ?? '' }}</td><td>{{ $entry['plantilla_position'] ?? '' }}</td><td>{{ $entry['appointment_status'] ?? '' }}</td><td>{{ $entry['start_date'] ?? '' }}</td><td>{{ $entry['end_date'] ?? '' }}</td><td>{{ $entry['monthly_salary'] ?? '' }}</td></tr>
                            @empty
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    <table class="cv-table cv-detail-table cv-specialization-table">
                        <colgroup><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading"><th colspan="2">FIELD OF SPECIALIZATION</th></tr>
                            <tr class="cv-detail-heading"><th>Field of Specialization</th><th>Primary Field<span class="cv-header-hint">(Yes or No)</span></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($person['specializations'] as $entry)
                                <tr class="cv-data-row"><td>{{ $entry['field'] ?? '' }}</td><td>{{ $entry['primary_field'] ?? '' }}</td></tr>
                            @empty
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    <table class="cv-table cv-detail-table cv-awards-table">
                        <colgroup><col><col><col><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading"><th colspan="5">R&amp;D AWARDS</th></tr>
                            <tr class="cv-detail-heading"><th>Title of R&amp;D Award</th><th>Rank</th><th>Category<span class="cv-header-hint">(Local - In-house, Local – Regional, Local – National, International)</span></th><th>Granting Institution</th><th>Year Granted<span class="cv-header-hint">(yyyy)</span></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($person['awards'] as $entry)
                                <tr class="cv-data-row"><td>{{ $entry['title'] ?? '' }}</td><td>{{ $entry['rank'] ?? '' }}</td><td>{{ $entry['category'] ?? '' }}</td><td>{{ $entry['granting_institution'] ?? '' }}</td><td>{{ $entry['year_granted'] ?? '' }}</td></tr>
                            @empty
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    <table class="cv-table cv-detail-table cv-projects-table">
                        <colgroup><col><col><col><col><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading"><th colspan="6">R&amp;D PROJECTS HEADED/CONDUCTED</th></tr>
                            <tr class="cv-detail-heading"><th>Title of R&amp;D Project</th><th>Designation<span class="cv-header-hint">(Co-Program Leader/ Co-project Leader/Program Coordinator/ Program Leader/Project Leader/ Research &amp; Development Staff)</span></th><th>Sector<span class="cv-header-hint">(Agricultural Resources Management, Crops, Forestry and Env’t, Livestock, Socio-economics)</span></th><th>Current Status<span class="cv-header-hint">(Approved/Completed with TR/Completed w/o TR/ Deferred (approval)/ Deferred (impl’n)/ Disapproved/Extended New/Ongoing/Proposed Reactivated/Recommended Rejected/Suspended Terminated/Terminated w/ TR)</span></th><th colspan="2">Year <span class="cv-header-hint">(yyyy)</span></th></tr>
                            <tr class="cv-detail-heading cv-detail-subheading cv-projects-subheading"><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>From</th><th>To</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($person['projects'] as $entry)
                                <tr class="cv-data-row"><td>{{ $entry['title'] ?? '' }}</td><td>{{ $entry['designation'] ?? '' }}</td><td>{{ $entry['sector'] ?? '' }}</td><td>{{ $entry['current_status'] ?? '' }}</td><td>{{ $entry['year_from'] ?? '' }}</td><td>{{ $entry['year_to'] ?? '' }}</td></tr>
                            @empty
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="cv-source-table-gap" aria-hidden="true"></div>

                    <table class="cv-table cv-detail-table cv-publications-table">
                        <colgroup><col><col><col><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading cv-section-heading-centered"><th colspan="5">R&amp;D RELATED PUBLICATIONS (for the last 3 years)</th></tr>
                            <tr class="cv-detail-heading"><th>Title of R&amp;D Publication</th><th>Year Published<span class="cv-header-hint">(yyyy)</span></th><th>Place of Publication</th><th>Publication Group<span class="cv-header-hint">(R &amp; D Papers in Scientific Journals/Technical Reports/Research Abstracts/Papers Presented in Conferences/Books/ News Articles)</span></th><th>Authoring Type<span class="cv-header-hint">(Sole-Author/Co-Author/Editor/Co-Editor/Main Author)</span></th></tr>
                        </thead>
                        <tbody>
                            @forelse ($person['publications'] as $entry)
                                <tr class="cv-data-row"><td>{{ $entry['title'] ?? '' }}</td><td>{{ $entry['year_published'] ?? '' }}</td><td>{{ $entry['place'] ?? '' }}</td><td>{{ $entry['publication_group'] ?? '' }}</td><td>{{ $entry['authoring_type'] ?? '' }}</td></tr>
                            @empty
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    <table class="cv-table cv-detail-table cv-presentations-table">
                        <colgroup><col><col><col><col><col><col></colgroup>
                        <thead>
                            <tr class="cv-section-heading cv-section-heading-centered"><th colspan="6">R&amp;D PRESENTATION (for the last 3 years)</th></tr>
                            <tr class="cv-detail-heading"><th>Title of Research Paper</th><th>Conference Title</th><th>Category<span class="cv-header-hint">(Local - In-house, Local – Regional, Local – National, International)</span></th><th>Date</th><th>Venue</th><th>Sponsor</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($person['presentations'] as $entry)
                                <tr class="cv-data-row"><td>{{ $entry['title'] ?? '' }}</td><td>{{ $entry['conference_title'] ?? '' }}</td><td>{{ $entry['category'] ?? '' }}</td><td>{{ $entry['date'] ?? '' }}</td><td>{{ $entry['venue'] ?? '' }}</td><td>{{ $entry['sponsor'] ?? '' }}</td></tr>
                            @empty
                                <tr class="cv-data-row"><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </main>
        @endforeach
    </body>
</html>
