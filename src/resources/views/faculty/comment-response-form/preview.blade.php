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
            <a href="{{ route('faculty.topics.comment-response-form.pdf', ['topic' => $topic, 'source' => $commentResponseForm['form_source'], 'review' => $commentResponseForm['review_id']]) }}" target="_blank" rel="noopener">Open PDF</a>
        </nav>
        <main class="comment-response-sheet" aria-label="BatStateU Comment-Response Form">
            <header class="university-header">
                <img src="{{ asset('images/batstateu-logo.png') }}" alt="Batangas State University seal">
                <div>Republic of the Philippines<strong>BATANGAS STATE UNIVERSITY</strong><span>The National Engineering University</span></div>
            </header>
            <h1>MATRIX ON THE ACTIONS MADE FOR THE COMMENTS AND SUGGESTIONS</h1>
            @php
                $stages = array_intersect(array_keys(\App\Services\CommentResponseFeedback::STAGE_LABELS), $commentResponseForm['evaluation_stages'] ?? []);
                $evaluationLevels = [
                    ['label' => 'Initial Screening', 'checked' => count(array_diff($stages, ['lrec'])) > 0],
                    ['label' => 'Local Research Evaluation', 'checked' => in_array('lrec', $stages, true)],
                ];
                $researchers = array_filter([$commentResponseForm['project_leader'], ...array_column($commentResponseForm['staff'], 'name')]);
            @endphp
            <section class="evaluation" aria-label="Level of evaluation done">
                <p><strong>LEVEL OF EVALUATION DONE:</strong></p>
                <ul class="evaluation-levels">
                    @foreach ($evaluationLevels as $level)
                        <li><span class="evaluation-box {{ $level['checked'] ? 'is-checked' : '' }}" aria-hidden="true"></span>{{ $level['label'] }}<span class="sr-only">{{ $level['checked'] ? ' — Selected' : ' — Not selected' }}</span></li>
                    @endforeach
                </ul>
            </section>
            <p><strong>TITLE OF RESEARCH PROPOSAL:</strong> <strong>{{ $commentResponseForm['project_title'] }}</strong></p>
            <p><strong>PROJECT STAFF:</strong> <strong>{{ implode(', ', $researchers) }}</strong></p>
            <table class="feedback">
                <colgroup><col class="number"><col class="comments"><col class="response"><col class="remarks"></colgroup>
                <thead><tr><th>NO.</th><th>COMMENTS AND SUGGESTIONS</th><th>ACTION AND RESPONSE<small>(Changes made in the revised proposal)</small></th><th>REMARKS<small>Page and paragraph number of the changes made</small></th></tr></thead>
                <tbody>
                    @forelse ($commentResponseForm['feedback'] as $item)
                        <tr>
                            <td>{{ $loop->iteration }}.</td>
                            <td class="comment">{{ $item['comment'] }}</td>
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
                    <p><strong>{{ ($commentResponseForm['comment_response_head'] ?? '') ?: config('work_plan.verifier.name') }}</strong><br>Research Head / RDES Head<br>Member, LREC</p>
                    <p><strong>{{ ($commentResponseForm['comment_response_vice_chancellor'] ?? '') ?: config('notice_to_proceed.issuing_officer.name') }}</strong><br>Vice Chancellor for Research,<br>Development and Extension Services<br>Member, LREC</p>
                </div>
            </section>
        </main>
    </body>
</html>
