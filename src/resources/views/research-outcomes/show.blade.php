<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Post-implementation" :subtitle="$outcome->projectTitle()">
            <x-slot name="actions"><x-back-link href="{{ route('research-outcomes.index') }}">Back to projects</x-back-link></x-slot>
        </x-page-header>
    </x-slot>
    <div class="mx-auto max-w-3xl space-y-5">
        @if (session('success'))<p role="status" class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-950/30 dark:text-green-200">{{ session('success') }}</p>@endif
        <section class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-950">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $outcome->projectTitle() }}</h2>
            <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <div><dt class="text-slate-500 dark:text-slate-400">Team leader</dt><dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ $outcome->projectLeader()->name }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">College</dt><dd class="mt-1 text-slate-900 dark:text-slate-100">{{ $outcome->projectLeader()->college ?: 'Not set' }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Funding</dt><dd class="mt-1 text-slate-900 dark:text-slate-100">{{ App\Models\ResearchOutcome::FUNDING_SOURCES[$outcome->funding_source] }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Last update</dt><dd class="mt-1 text-slate-900 dark:text-slate-100">{{ $outcome->updated_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') ?? 'Not updated' }}</dd></div>
            </dl>
            @php
                $members = $outcome->topic_id
                    ? $outcome->topic->collaborators->concat($outcome->topic->revisionDraft?->members ?? collect())
                        ->whereNotNull('accepted_at')
                        ->reject(fn ($member) => $member->isProjectSecretary())
                        ->map(fn ($member) => $member->user?->name ?? $member->name)
                        ->filter()->unique(fn ($name) => mb_strtolower(trim($name)))->implode(', ')
                    : $outcome->team_members;
            @endphp
            @if ($members)<p class="mt-4 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">Other team members: {{ $members }}</p>@endif
        </section>

        @can('update', $outcome)
            <form method="POST" action="{{ $outcome->topic_id ? route('research-outcomes.projects.update', $outcome->topic_id) : route('research-outcomes.update', $outcome) }}" class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-950">
                @csrf
                @method('PUT')
                @include('research-outcomes.form', ['context' => $outcome->topic_id ? 'project:'.$outcome->topic_id : 'record:'.$outcome->id])
                <p class="text-sm text-slate-600 dark:text-slate-300">Changes are sent to your college coordinator.</p>
                <x-primary-button>Save labels</x-primary-button>
            </form>
        @else
            <section class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-950">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">Project labels</h2>
                <ul class="flex flex-wrap gap-2">
                    @forelse ($outcome->labels as $label)<li class="rounded-md bg-red-50 px-3 py-2 text-sm font-medium text-brand dark:bg-red-950/40 dark:text-red-200">{{ App\Models\ResearchOutcome::LABELS[$label] }}</li>
                    @empty<li class="text-sm text-slate-500 dark:text-slate-400">No labels recorded</li>@endforelse
                </ul>
                <p class="text-sm text-slate-600 dark:text-slate-300">The team leader maintains these labels.</p>
            </section>
        @endcan
    </div>
</x-app-layout>
