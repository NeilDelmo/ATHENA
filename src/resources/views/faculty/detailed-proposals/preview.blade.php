@php
    $paragraphs = fn (string $text): array => collect(preg_split('/\R+/u', $text) ?: [])
        ->map(fn (string $line): string => trim($line))
        ->filter()
        ->values()
        ->all();
    $sdgs = config('detailed_proposal.sdgs');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>BatStateU-FO-RES-02 - Detailed Research Proposal Preview</title>
        @vite('resources/css/detailed-proposal-print.css')
    </head>
    <body class="detailed-proposal-preview-page">
        <main class="detailed-proposal-sheet" aria-label="BatStateU-FO-RES-02 Detailed Research Proposal">
            <table class="detailed-proposal-table">
                <colgroup><col class="detailed-proposal-col-seal"><col class="detailed-proposal-col-reference"><col class="detailed-proposal-col-effectivity"><col class="detailed-proposal-col-revision"></colgroup>
                <tbody>
                    <tr>
                        <th class="detailed-proposal-header-cell"><img class="detailed-proposal-seal" src="{{ asset('images/batstateu-logo.png') }}" alt="Batangas State University seal"></th>
                        <td class="detailed-proposal-header-cell">Reference No.: BatStateU-FO-RES-02</td>
                        <td class="detailed-proposal-header-cell">Effectivity Date: August 22, 2023</td>
                        <td class="detailed-proposal-header-cell">Revision No.: 04</td>
                    </tr>
                    <tr><th colspan="4" class="detailed-proposal-table-title">DETAILED RESEARCH PROPOSAL</th></tr>
                    <tr>
                        <td colspan="4">
                            <p class="detailed-proposal-section-heading">I. Research Project Title:</p>
                            <p class="detailed-proposal-section-value">{{ $detailedProposal['project_title'] }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading">II. BatStateU Research Agenda:</span> <span class="detailed-proposal-section-value">{{ $detailedProposal['research_agenda'] }}</span></p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading">III. Sustainable Development Goal:</span> <span class="detailed-proposal-section-note">(Check all applicable SDG)</span></p>
                            <table class="detailed-proposal-sdg-table">
                                <tbody>
                                    @foreach ([[1, 10], [2, 11], [3, 12], [4, 13], [5, 14], [6, 15], [7, 16], [8, 17], [9, null]] as [$leftSdg, $rightSdg])
                                        <tr>
                                            <td><span class="detailed-proposal-sdg-box">{{ in_array($leftSdg, $detailedProposal['sdgs'], true) ? '☒' : '☐' }}</span> SDG{{ $leftSdg }}: {{ $sdgs[$leftSdg] }}</td>
                                            <td>@if ($rightSdg !== null)<span class="detailed-proposal-sdg-box">{{ in_array($rightSdg, $detailedProposal['sdgs'], true) ? '☒' : '☐' }}</span> SDG{{ $rightSdg }}: {{ $sdgs[$rightSdg] }}@endif</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <div class="detailed-proposal-member-block">
                                <p><span class="detailed-proposal-section-heading">IV. Project Leader:</span> <span class="detailed-proposal-section-value">{{ $detailedProposal['project_leader'] }}</span></p>
                                <p class="detailed-proposal-indent">Email Address: {{ $detailedProposal['leader_email'] }}</p>
                                <p class="detailed-proposal-indent">Contact Number: {{ $detailedProposal['leader_contact'] }}</p>
                            </div>
                            @foreach ($detailedProposal['staff'] as $member)
                                <div class="detailed-proposal-member-block">
                                    <p><span class="detailed-proposal-section-heading">Project Staff (s):</span> <span class="detailed-proposal-section-value">{{ $member['name'] }}</span></p>
                                    <p class="detailed-proposal-indent">Email Address: {{ $member['email'] }}</p>
                                    <p class="detailed-proposal-indent">Contact Number: {{ $member['contact'] }}</p>
                                </div>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading">V. Proponent Agency:</span> <span class="detailed-proposal-section-value">{{ $detailedProposal['proponent_agency'] }}</span></p>
                            <p class="detailed-proposal-indent"><span class="detailed-proposal-section-heading">Department:</span> <span class="detailed-proposal-section-value">{{ $detailedProposal['proponent_department'] }}</span></p>
                            <p class="detailed-proposal-indent"><span class="detailed-proposal-section-heading">College:</span> <span class="detailed-proposal-section-value">{{ $detailedProposal['proponent_college'] }}</span></p>
                            <p class="detailed-proposal-indent"><span class="detailed-proposal-section-heading">Campus:</span> <span class="detailed-proposal-section-value">{{ $detailedProposal['proponent_campus'] }}</span></p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading">VI. Cooperating Agency:</span> <span class="detailed-proposal-section-note">(if any)</span> {{ $detailedProposal['cooperating_agency'] ?: 'None' }}</p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-narrative">
                            <p class="detailed-proposal-section-heading">VII. Executive Brief:</p>
                            @foreach ($paragraphs($detailedProposal['executive_brief']) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-narrative">
                            <p><span class="detailed-proposal-section-heading">VIII. Rationale:</span> <span class="detailed-proposal-section-note">(include available statistics related to the problem)</span></p>
                            @foreach ($paragraphs($detailedProposal['rationale']) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-narrative">
                            <p class="detailed-proposal-section-heading">IX. Objectives of the Project:</p>
                            @foreach ($paragraphs($detailedProposal['objectives']) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading">X. Expected Output of the Project:</span> <span class="detailed-proposal-section-note">(based on expanded 6Ps &amp; 2Is of research)</span></p>
                            <ol class="detailed-proposal-output-list">
                                @foreach (config('detailed_proposal.expected_outputs') as $key => $label)
                                    <li>{{ $label }}: {{ $detailedProposal['expected_outputs'][$key] }}</li>
                                @endforeach
                            </ol>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-narrative">
                            <p><span class="detailed-proposal-section-heading">XI. Review of Related Literature:</span> <span class="detailed-proposal-section-note">(minimum of ten literature/studies reviewed)</span></p>
                            @foreach ($paragraphs($detailedProposal['related_literature']) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-narrative">
                            <p class="detailed-proposal-section-heading">XII. Methodology:</p>
                            @foreach (config('detailed_proposal.methodology') as $key => $label)
                                <p class="detailed-proposal-methodology-heading">{{ $label }}</p>
                                @foreach ($paragraphs($detailedProposal['methodology'][$key]) as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-narrative">
                            <p class="detailed-proposal-section-heading">XIII. Duties and Responsibilities of each member:</p>
                            @foreach ($detailedProposal['responsibilities'] as $responsibility)
                                <p class="detailed-proposal-responsibility-name">{{ $loop->first ? 'Project Leader' : 'Project Staff (s)' }}: {{ $responsibility['name'] }}</p>
                                @foreach ($paragraphs($responsibility['duties']) as $paragraph)
                                    <p>{{ $paragraph }}</p>
                                @endforeach
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading">XIV. Major Activities/Workplan (Gantt Chart):</span> See attached Form A</p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading">XV. Line-Item Budget:</span> See attached Form B</p>
                            <table class="detailed-proposal-budget-table">
                                <tbody>
                                    <tr><td class="detailed-proposal-budget-number">1.</td><td><strong>Maintenance and Operating Expenses</strong></td><td class="detailed-proposal-budget-amount">Php {{ number_format($detailedProposal['mooe_total'], 2) }}</td></tr>
                                    <tr><td class="detailed-proposal-budget-number">2.</td><td><strong>Capital Outlay and Equipment</strong></td><td class="detailed-proposal-budget-amount">Php {{ number_format($detailedProposal['co_total'], 2) }}</td></tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-narrative">
                            <p class="detailed-proposal-section-heading">XVI. References:</p>
                            @foreach ($paragraphs($detailedProposal['references']) as $paragraph)
                                <p>{{ $paragraph }}</p>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><span class="detailed-proposal-section-heading">XVII. Curriculum Vitae:</span> See attached Form C</p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <p><strong>*</strong>Required Attachment (use appropriate ISO Form): (A) Major Activities and Work Plan; (B) Line-item Budget; (C) Curriculum Vitae</p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" rowspan="3" class="detailed-proposal-signature-cell">
                            <p class="detailed-proposal-signature-heading">Prepared by:</p>
                            <p class="detailed-proposal-signature-space">&nbsp;</p>
                            <p class="detailed-proposal-signature-name">{{ $detailedProposal['project_leader'] }}</p>
                            <p>Project Leader</p>
                            <p class="detailed-proposal-signature-date">Date Signed:</p>
                        </td>
                        <td colspan="2"><p><span class="detailed-proposal-section-heading">Department:</span> {{ $detailedProposal['proponent_department'] }}</p></td>
                    </tr>
                    <tr><td colspan="2"><p><span class="detailed-proposal-section-heading">College:</span> {{ $detailedProposal['proponent_college'] }}</p></td></tr>
                    <tr><td colspan="2"><p><span class="detailed-proposal-section-heading">Campus:</span> {{ $detailedProposal['proponent_campus'] }}</p></td></tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-privacy">Pursuant to Republic Act No. 10173, also known as the Data Privacy Act of 2012, the Batangas State University, the National Engineering University recognizes its commitment to protect and respect the privacy of its customers and/or stakeholders and ensure that all information collected from them are all processed in accordance with the principles of transparency, legitimate purpose and proportionality mandated under the Data Privacy Act of 2012.</td>
                    </tr>
                    <tr><td colspan="4" class="detailed-proposal-office-heading">To be accomplished by the Research Office</td></tr>
                    <tr>
                        <td colspan="2" class="detailed-proposal-checklist">
                            <p>Checklist:</p>
                            <p>☐ Complete Documents</p>
                            <div class="detailed-proposal-sub-items">
                                <p>Detailed Proposal</p>
                                <p>LIB</p>
                                <p>Work Plan</p>
                            </div>
                            <p>☐ Initial Screening Form</p>
                            <p>Score: _______</p>
                        </td>
                        <td colspan="2" class="detailed-proposal-checklist">
                            <p><strong>Level of Call</strong></p>
                            <p>☐ Central Agency (VPRDES, President)</p>
                            <p>☐ Constituent Campus (VCRDES, Chancellor)</p>
                        </td>
                    </tr>
                    <tr><td colspan="4" class="detailed-proposal-office-heading">To be accomplished by the Researcher/s</td></tr>
                    <tr>
                        <td colspan="2" class="detailed-proposal-signature-cell">
                            <p class="detailed-proposal-signature-heading">Checked and Verified by:</p>
                            <p class="detailed-proposal-signature-space">&nbsp;</p>
                            <p class="detailed-proposal-signature-line">________________________________</p>
                            <p>NAME</p>
                            <p>Director, Research / Head, Research / Head, Research &amp; Extension</p>
                            <p class="detailed-proposal-signature-date">Date Signed:</p>
                        </td>
                        <td colspan="2" class="detailed-proposal-signature-cell">
                            <p class="detailed-proposal-signature-heading">Recommending Approval:</p>
                            <p class="detailed-proposal-signature-space">&nbsp;</p>
                            <p class="detailed-proposal-signature-line">________________________________</p>
                            <p>NAME</p>
                            <p>Vice President/Vice Chancellor for Research Development and Extension Services</p>
                            <p class="detailed-proposal-signature-date">Date Signed:</p>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="detailed-proposal-signature-cell">
                            <p class="detailed-proposal-signature-heading">Approved by the Research Council/Local Research Evaluation Committee-Chair (LREC-Chair) Represented by:</p>
                            <p class="detailed-proposal-signature-space">&nbsp;</p>
                            <p class="detailed-proposal-signature-line">__________________________________</p>
                            <p>NAME</p>
                            <p>University President/Vice President for RDES</p>
                            <p class="detailed-proposal-signature-date">Date Signed:</p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="detailed-proposal-notes">
                <p class="detailed-proposal-note-heading">Notes: The Signatories funded by:</p>
                <p class="detailed-proposal-note-indented"><u>Approval through Research Council</u></p>
                <p class="detailed-proposal-note-indented detailed-proposal-note-detail">Director, Research; Vice President for RDES: &amp; University President</p>
                <p class="detailed-proposal-note-indented"><u>Approval through Local Research Evaluation Committee</u></p>
                <p class="detailed-proposal-note-indented detailed-proposal-note-detail">Head, Research/Head Research &amp; Extension; Vice Chancellor for RDES; &amp; Vice President for RDES</p>
            </div>

            <footer><span>Tracking No. ________________</span><span>&nbsp;</span></footer>
        </main>
    </body>
</html>
