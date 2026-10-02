<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Proposal signatories" :subtitle="$proposalDraft->project_title">
            <x-slot name="actions">
                <x-back-link fixed href="{{ $returnUrl }}">Back to proposal paper</x-back-link>
            </x-slot>
        </x-page-header>
    </x-slot>
    <div class="mx-auto w-full max-w-7xl space-y-6 py-6 sm:px-6 lg:px-8" data-proposal-signatories-workspace>
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
        <p class="text-sm text-gray-600 dark:text-gray-300">Research Head and VCRDES names are filled automatically on each paper. Project-leader names come from Project Details. Other signature roles use the Research Head’s directory.</p>
        <form action="{{ route('signatories.select', $proposalDraft) }}" method="POST" class="space-y-4">@csrf @method('PUT')
            <input type="hidden" name="lock_version" value="{{ $proposalDraft->lock_version }}">
            @if ($returnPaper !== '')
                <input type="hidden" name="return_paper" value="{{ $returnPaper }}">
            @endif
            @foreach($groups as $paper => $fields)
                <fieldset class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-6">
                    <legend class="px-2 text-sm font-bold dark:text-white">{{ str($paper)->replace('_', ' ')->title() }}</legend>
                    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($fields as $key => $label)
                        @php
                            $saved = $proposalDraft->resolvedSignatorySelections()[$key] ?? null;
                            $isDefault = array_key_exists($key, \App\Models\ProposalSignatory::defaultSelections());
                            $people = $options->get($key, collect());
                        @endphp
                        <label class="text-sm text-gray-700 dark:text-gray-200">{{ $label }}
                            @if ($isDefault)
                                <span data-default-signatory="{{ $key }}" class="mt-1 block rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold dark:border-gray-700 dark:bg-gray-800">{{ $saved['name'] }}</span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ $saved['position'] }} · Filled automatically</span>
                            @else
                            <select name="signatories[{{ $key }}]" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:bg-gray-800">
                                <option value="">{{ $saved ? 'Keep: '.$saved['name'].' — '.($saved['position'] ?? '') : 'Select a name' }}</option>
                                @foreach($people as $person)<option value="{{ $person->id }}" @selected((string) old('signatories.'.$key) === (string) $person->id)>{{ $person->name }} — {{ $person->position }}</option>@endforeach
                            </select>
                            @if($people->isEmpty())<span class="mt-1 block text-xs text-amber-700 dark:text-amber-300">Ask the Research Head to add a name for this role.</span>@endif
                            @endif
                        </label>
                    @endforeach
                    </div>
                </fieldset>
            @endforeach
            <button class="rounded-lg bg-red-700 px-5 py-2.5 text-sm font-semibold text-white">Save signatories and return</button>
        </form>
    </div>
</x-app-layout>
