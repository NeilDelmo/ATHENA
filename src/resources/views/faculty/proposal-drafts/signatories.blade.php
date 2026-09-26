<x-app-layout>
    <x-slot name="header"><x-back-link fixed href="{{ $returnUrl }}">Back to proposal paper</x-back-link><h2 class="mt-3 text-xl font-bold dark:text-white">Choose proposal signatories</h2><p class="mt-1 text-sm text-gray-500">{{ $proposalDraft->project_title }}</p></x-slot>
    <div class="mx-auto max-w-4xl space-y-4 py-6 sm:px-6">
        @if (session('success'))
            <x-proposal-alert>{{ session('success') }}</x-proposal-alert>
        @endif
        @if ($errors->any())
            <x-proposal-alert type="error">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </x-proposal-alert>
        @endif
        <p class="text-sm text-gray-600 dark:text-gray-300">Choose names supplied by the Research Head. Project-leader names come from Project Details. Attachment C (CV) and Estimated Expense Breakdown do not require signatures.</p>
        <form action="{{ route('signatories.select', $proposalDraft) }}" method="POST" class="space-y-4">@csrf @method('PUT')
            <input type="hidden" name="lock_version" value="{{ $proposalDraft->lock_version }}">
            @if ($returnPaper !== '')
                <input type="hidden" name="return_paper" value="{{ $returnPaper }}">
            @endif
            @foreach($groups as $paper => $fields)
                <fieldset class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                    <legend class="px-2 text-sm font-bold dark:text-white">{{ str($paper)->replace('_', ' ')->title() }}</legend>
                    <div class="grid gap-4 sm:grid-cols-2">
                    @foreach($fields as $key => $label)
                        @php
                            $saved = $proposalDraft->signatory_selections[$key] ?? null;
                            $people = $options->get($key, collect());
                        @endphp
                        <label class="text-sm text-gray-700 dark:text-gray-200">{{ $label }}
                            <select name="signatories[{{ $key }}]" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-800">
                                <option value="">{{ $saved ? 'Keep: '.$saved['name'].' — '.$saved['position'] : 'Select a name' }}</option>
                                @foreach($people as $person)<option value="{{ $person->id }}" @selected((string) old('signatories.'.$key) === (string) $person->id)>{{ $person->name }} — {{ $person->position }}</option>@endforeach
                            </select>
                            @if($people->isEmpty())<span class="mt-1 block text-xs text-amber-700 dark:text-amber-300">Ask the Research Head to add a name for this role.</span>@endif
                        </label>
                    @endforeach
                    </div>
                </fieldset>
            @endforeach
            <button class="rounded-lg bg-red-700 px-5 py-2.5 text-sm font-semibold text-white">Save signatories and return</button>
        </form>
    </div>
</x-app-layout>
