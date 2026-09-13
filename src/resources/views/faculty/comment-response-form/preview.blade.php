<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $commentResponseForm['form_label'] }} — {{ $commentResponseForm['project_title'] }}</title>
        @vite('resources/css/comment-response-form-print.css')
    </head>
    <body>
        <nav class="preview-toolbar" aria-label="Form actions">
            <span>{{ $commentResponseForm['form_label'] }}</span>
            <a href="{{ route('faculty.topics.comment-response-form.pdf', ['topic' => $topic, 'source' => $commentResponseForm['form_source'], 'review' => $commentResponseForm['review_id']]) }}">Download PDF</a>
            <span><a href="{{ route('faculty.topics.comment-response-form.download', ['topic' => $topic, 'source' => $commentResponseForm['form_source'], 'review' => $commentResponseForm['review_id']]) }}" style="background: transparent; color: inherit; text-decoration: underline;">Word (editable)</a></span>
        </nav>
        <main class="comment-response-sheet" aria-label="BatStateU Comment-Response Form">
            <header class="university-header">
                <img src="{{ asset('images/batstateu-logo.png') }}" alt="Batangas State University seal">
                <div>Republic of the Philippines<strong>BATANGAS STATE UNIVERSITY</strong><span>The National Engineering University</span></div>
            </header>
            <h1>COMMENT-RESPONSE FORM</h1>
            <p><strong>REVIEW SOURCE:</strong> {{ $commentResponseForm['form_label'] }}</p>
            <p class="evaluation">PREVIOUS EVALUATION DONE:<br>☐ Initial Screening<br>☐ Evaluation by the Local Research Evaluation Committee (LREC)<br><span>(date presented: ______________)</span></p>
            <p><strong>TITLE OF RESEARCH PROPOSAL:</strong><br>{{ $commentResponseForm['project_title'] }}</p>
            <p><strong>RESEARCHERS:</strong></p>
            <table class="researchers">
                <thead><tr><th>POSITION</th><th>NAME</th><th>CAMPUS</th><th>COLLEGE</th><th>DEPARTMENT</th></tr></thead>
                <tbody>
                    <tr><td>Project Leader</td><td>{{ $commentResponseForm['project_leader'] }}</td><td>{{ $commentResponseForm['leader_campus'] }}</td><td>{{ $commentResponseForm['leader_college'] }}</td><td>{{ $commentResponseForm['leader_department'] }}</td></tr>
                    @foreach ($commentResponseForm['staff'] as $member)
                        <tr><td>Project Staff</td><td>{{ $member['name'] }}</td><td>{{ $member['campus'] }}</td><td>{{ $member['college'] }}</td><td>{{ $member['department'] }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            <table class="feedback">
                <colgroup><col class="number"><col class="comments"><col class="response"><col class="remarks"></colgroup>
                <thead><tr><th>NO.</th><th>COMMENTS AND SUGGESTIONS</th><th>ACTION AND RESPONSE<small>(Changes made in the revised proposal)</small></th><th>REMARKS<small>Page and paragraph number of the changes made</small></th></tr></thead>
                <tbody>
                    @forelse ($commentResponseForm['feedback'] as $item)
                        <tr>
                            <td>{{ $loop->iteration }}.</td>
                            <td><strong class="reviewer">{{ $item['reviewer'] }}</strong><span class="location">{{ $item['location'] }}</span><div class="comment">{{ $item['comment'] }}</div></td>
                            <td class="comment">{{ $item['response'] }}</td><td class="comment">{{ $item['remarks'] }}</td>
                        </tr>
                    @empty
                        <tr><td>1.</td><td></td><td></td><td></td></tr>
                        <tr><td>2.</td><td></td><td></td><td></td></tr>
                    @endforelse
                </tbody>
            </table>
            <section class="signatures">
                <p>Prepared by:</p>
                <p class="signature-name"><strong>{{ $commentResponseForm['project_leader'] }}</strong><br>Project Leader</p>
                <p>Checked and Reviewed by:</p>
                <div class="review-signatures">
                    <p>____________________________<br>Research Head / RDES Head<br>Member, LREC</p>
                    <p>____________________________<br>Vice Chancellor for Research,<br>Development and Extension Services<br>Member, LREC</p>
                </div>
            </section>
            <footer>{{ $commentResponseForm['form_label'] }} | {{ $commentResponseForm['project_title'] }}</footer>
        </main>
    </body>
</html>
