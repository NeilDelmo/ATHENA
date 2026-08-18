<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Notice to Proceed Preview</title>
        @vite('resources/css/notice-to-proceed-print.css')
    </head>
    <body class="notice-to-proceed-preview-page">
        <main data-notice-to-proceed-sheet class="notice-to-proceed-sheet" aria-label="Notice to Proceed preview">
            <header class="notice-to-proceed-letterhead">
                <img src="{{ asset('images/batstateu-logo.png') }}" alt="Batangas State University seal">
                <div>
                    <p>Republic of the Philippines</p>
                    <p class="notice-to-proceed-university">BATANGAS STATE UNIVERSITY</p>
                    <p class="notice-to-proceed-subtitle">The National Engineering University</p>
                    <p class="notice-to-proceed-campus">ARASOF-Nasugbu Campus</p>
                    <p>R. Martinez St., Brgy. Bucana, Nasugbu, Batangas, Philippines 4231</p>
                    <p>Tel Nos.: +63 43 416 0350 local 302</p>
                    <p>E-mail: research.nasugbu@g.batstate-u.edu.ph &nbsp;|&nbsp; www.batstate-u.edu.ph</p>
                </div>
            </header>

            <div class="notice-to-proceed-office-heading">Research Office</div>

            <section class="notice-to-proceed-letter-body">
                <p class="notice-to-proceed-date">{{ $notice['NOTICE_DATE'] }}</p>
                <p class="notice-to-proceed-recipients">{{ $notice['RESEARCHER_NAMES'] }}</p>
                <p>{{ $notice['CAMPUS_LINE'] }}</p>

                <p class="notice-to-proceed-greeting">Dear Researchers:</p>

                <p>You are hereby awarded this <strong>NOTICE TO PROCEED</strong> for the institutionally approved research project entitled <strong>“{{ $notice['PROJECT_TITLE'] }}.”</strong></p>

                <p>Based on the Local Research Evaluation Committee (LREC) Resolution No. {{ $notice['RESOLUTION_NUMBER'] }}, S. {{ $notice['RESOLUTION_YEAR'] }}, the approved duration of the project is {{ $notice['DURATION_WORDS'] }} ({{ $notice['DURATION_MONTHS'] }}) {{ $notice['DURATION_UNIT'] }} which shall commence from {{ $notice['START_DATE'] }} to {{ $notice['END_DATE'] }}, with a budget amounting to {{ $notice['BUDGET_WORDS'] }} (Php {{ $notice['BUDGET_AMOUNT'] }}) only. You are required to present monthly actual accomplishments to the assigned research monitoring personnel and submit quarterly monitoring reports to the Research Office of ARASOF-Nasugbu Campus during the conduct of this project.</p>

                <p>You are entitled to the reduction of teaching load during the approved duration of the project based on the University guidelines. Upon completion of the project, you shall be entitled to avail paper presentation support and publication support and incentives for the dissemination of your research outputs, and Technology Transfer support and incentives for the protection and commercialization of Intellectual Property assets that may be generated, subject to the existing policies of the University and availability of funds.</p>

                <table class="notice-to-proceed-load-table">
                    <thead>
                        <tr>
                            <th>Classification of Researcher</th>
                            <th>Reduction of Teaching Load</th>
                            <th>Hours Rendered Weekly in Research</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Regular Faculty</td>
                            <td>6 units per research project</td>
                            <td>12 hours</td>
                        </tr>
                        <tr>
                            <td>Faculty with administrative assignment/local designation</td>
                            <td>3 units per research project*</td>
                            <td>6 hours</td>
                        </tr>
                    </tbody>
                </table>

                <p class="notice-to-proceed-note">*A maximum of 3 units must be retained for each faculty with administrative assignment/local designation.<br>Maximum of two (2) projects at a time are allowed per faculty member.</p>

                <p>Any changes in the Line-Item Budget, project duration, scope, or composition of the project team shall be subject to the approval of the undersigned.</p>
                <p>Please acknowledge receipt and acceptance of this NOTICE by affixing your signature below.</p>
                <p>Congratulations!</p>

                <div class="notice-to-proceed-signatures">
                    <section>
                        <p>Respectfully,</p>
                        <div class="notice-to-proceed-signature-space"></div>
                        <p class="notice-to-proceed-signatory">{{ $notice['ISSUING_OFFICER_NAME'] }}</p>
                        <p>{{ $notice['ISSUING_OFFICER_TITLE'] }}</p>
                        <p>{{ $notice['ISSUING_OFFICER_COMMITTEE_ROLE'] }}</p>
                    </section>
                    <section>
                        <p>Checked and Verified by:</p>
                        <div class="notice-to-proceed-signature-space"></div>
                        <p class="notice-to-proceed-signatory">{{ $notice['VERIFYING_OFFICER_NAME'] }}</p>
                        <p>{{ $notice['VERIFYING_OFFICER_TITLE'] }}</p>
                        <p>{{ $notice['VERIFYING_OFFICER_COMMITTEE_ROLE'] }}</p>
                    </section>
                </div>

                <section class="notice-to-proceed-conforme">
                    <p>Conforme:</p>
                    <div class="notice-to-proceed-conforme-line"></div>
                    <p>Signature above printed name</p>
                    <p class="notice-to-proceed-conforme-date">Date: ____________________</p>
                </section>
            </section>
        </main>
    </body>
</html>
