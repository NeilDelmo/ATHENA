<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Comment-Response Form Preview</title>
        @vite('resources/css/comment-response-form-print.css')
    </head>
    <body class="comment-response-preview-page">
        <main class="comment-response-sheet" aria-label="BatStateU Comment-Response Form">
            <img src="{{ asset('images/comment-response-form-preview.png') }}" alt="" class="comment-response-source" aria-hidden="true">

            <span class="comment-response-project-title">{{ $commentResponseForm['project_title'] }}</span>

            @foreach ([['row' => 'leader', 'researcher' => ['name' => $commentResponseForm['project_leader'], 'campus' => $commentResponseForm['leader_campus'], 'college' => $commentResponseForm['leader_college'], 'department' => $commentResponseForm['leader_department']]], ...collect($commentResponseForm['staff'])->map(fn (array $member, int $index): array => ['row' => 'staff-'.($index + 1), 'researcher' => $member])->all()] as $entry)
                @foreach (['name', 'campus', 'college', 'department'] as $field)
                    @if ($entry['researcher'][$field] !== '')
                        <span class="comment-response-researcher comment-response-{{ $entry['row'] }} comment-response-{{ $field }}">{{ $entry['researcher'][$field] }}</span>
                    @endif
                @endforeach
            @endforeach

            <span class="comment-response-prepared-by">{{ $commentResponseForm['project_leader'] }}</span>
            <span class="comment-response-footer-title">{{ $commentResponseForm['project_title'] }}</span>
        </main>
    </body>
</html>
