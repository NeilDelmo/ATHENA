<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-bold text-gray-950 dark:text-white">Research-call schedule</h2></x-slot>
    <section class="mx-auto max-w-3xl rounded-2xl border border-gray-200 bg-white p-6 text-gray-950 shadow-sm dark:border-gray-800 dark:bg-gray-950 dark:text-white">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="text-xl font-bold">{{ $call->title }}</h3><p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $call->academic_year }} · {{ ucfirst($call->lifecycleStatus()) }} · {{ config('app.timezone') }}</p></div><a href="{{ route('research-calls.index') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold dark:border-gray-700">{{ Auth::user()->isUsingWorkspace('research_head') ? 'All research calls' : 'All announcements' }}</a></div>
        <p class="mt-5 whitespace-pre-line text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $call->description }}</p>
        @if (! Auth::user()->isUsingWorkspace('research_head'))
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">These dates describe this announcement. You can submit a proposal anytime without selecting a research call.</p>
        @endif
        <dl class="mt-5 divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($milestones as $field => $label)
                @if($call->{$field})<div class="flex flex-wrap justify-between gap-2 py-3 text-sm"><dt>{{ $label }}</dt><dd class="font-semibold">{{ $call->{$field}->format(in_array($field, ['opens_at', 'closes_at']) ? 'M j, Y · g:i A' : 'M j, Y') }}</dd></div>@endif
            @endforeach
        </dl>
        <a href="{{ route('dashboard') }}" class="mt-5 inline-block text-sm font-semibold text-[#7A0019] dark:text-red-300">&larr; Back to dashboard</a>
    </section>
</x-app-layout>