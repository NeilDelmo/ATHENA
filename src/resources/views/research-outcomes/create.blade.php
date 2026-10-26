<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Register other completed research">
            <x-slot name="actions"><x-back-link href="{{ route('research-outcomes.index') }}">Back to post-implementation</x-back-link></x-slot>
        </x-page-header>
    </x-slot>
    <div class="mx-auto max-w-3xl space-y-5">
        <p class="text-sm leading-6 text-slate-600 dark:text-slate-300">Use this form for completed externally funded or non-funded research. Completed school-funded projects already appear in Post-implementation. You will be recorded as the team leader.</p>
        <form method="POST" action="{{ route('research-outcomes.store') }}" class="space-y-6 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-950">
            @csrf
            @include('research-outcomes.form', ['outcome' => null, 'context' => 'create'])
            <x-primary-button>Register research</x-primary-button>
        </form>
    </div>
</x-app-layout>
