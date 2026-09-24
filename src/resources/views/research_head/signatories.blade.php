<x-app-layout>
    <x-slot name="header">
        <div class="mx-auto max-w-6xl">
            <a href="{{ route('research_head.dashboard') }}" data-back-to-dashboard class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-gray-500 transition hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:text-slate-400 dark:hover:text-red-300 dark:focus:ring-offset-slate-900">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg>
                Back to dashboard
            </a>

            <h2 class="text-[26px] font-bold tracking-tight text-[#201A15] dark:text-white">Proposal signatory directory</h2>
            <p class="mt-1 max-w-2xl text-sm leading-6 text-[#6B6258] dark:text-slate-400">Add the names and positions faculty can choose from when building signature blocks, so titles stay spelled the same way every time.</p>

            <div class="mt-4 flex flex-wrap gap-2.5" aria-label="Directory summary">
                <span class="rounded-lg border border-[#E7E2D8] bg-white px-3.5 py-2 text-[13px] text-[#6B6258] shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400"><strong class="mr-1 text-[15px] text-[#201A15] dark:text-white">{{ \Illuminate\Support\Number::format($summary['total']) }}</strong>total signatories</span>
                <span class="rounded-lg border border-[#E7E2D8] bg-white px-3.5 py-2 text-[13px] text-[#6B6258] shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400"><strong class="mr-1 text-[15px] text-[#201A15] dark:text-white">{{ \Illuminate\Support\Number::format($summary['active']) }}</strong>active</span>
                <span class="rounded-lg border border-[#E7E2D8] bg-white px-3.5 py-2 text-[13px] text-[#6B6258] shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400"><strong class="mr-1 text-[15px] text-[#201A15] dark:text-white">{{ \Illuminate\Support\Number::format($summary['roles']) }}</strong>signature roles</span>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-4" data-signatory-directory>
        @if (session('success'))
            <div role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800 dark:border-green-900 dark:bg-green-950/40 dark:text-green-300">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="grid items-start gap-6 min-[860px]:grid-cols-[320px_minmax(0,1fr)]">
            <section class="rounded-xl border border-[#E7E2D8] bg-white p-[22px] shadow-[0_1px_2px_rgba(32,26,21,0.04),0_8px_20px_-12px_rgba(32,26,21,0.10)] dark:border-slate-700 dark:bg-slate-900 min-[860px]:sticky min-[860px]:top-5" aria-labelledby="add-signatory-heading">
                <h3 id="add-signatory-heading" class="text-[15px] font-semibold text-[#201A15] dark:text-white">Add a signatory</h3>

                <form action="{{ route('signatories.store') }}" method="POST" class="mt-4 space-y-3.5">
                    @csrf
                    <input type="hidden" name="active" value="1">

                    <label class="block text-[12.5px] text-[#6B6258] dark:text-slate-300" for="new-signatory-role">
                        Signature role
                        <select id="new-signatory-role" name="role_key" required class="mt-1.5 block w-full rounded-lg border-[#E7E2D8] bg-white px-3 py-2.5 text-sm text-[#201A15] shadow-none focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            @foreach ($roles as $key => $label)
                                <option value="{{ $key }}" @selected(old('role_key') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block text-[12.5px] text-[#6B6258] dark:text-slate-300" for="new-signatory-name">
                        Full name
                        <input id="new-signatory-name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name" placeholder="e.g. Dr. Ana M. Reyes" class="mt-1.5 block w-full rounded-lg border-[#E7E2D8] bg-white px-3 py-2.5 text-sm text-[#201A15] placeholder:text-gray-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500">
                    </label>

                    <label class="block text-[12.5px] text-[#6B6258] dark:text-slate-300" for="new-signatory-position">
                        Position / designation
                        <input id="new-signatory-position" name="position" value="{{ old('position') }}" required maxlength="120" placeholder="e.g. Dean, College of Engineering" class="mt-1.5 block w-full rounded-lg border-[#E7E2D8] bg-white px-3 py-2.5 text-sm text-[#201A15] placeholder:text-gray-400 focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:placeholder:text-slate-500">
                    </label>

                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-[#C1272D] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#9C1E23] focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900">Add name</button>
                    <p class="text-xs leading-5 text-[#6B6258] dark:text-slate-400">Faculty will pick from this list instead of typing names, so titles stay consistent across proposals.</p>
                </form>
            </section>

            <div class="min-w-0">
                <form
                    method="GET"
                    action="{{ route('signatories.index') }}"
                    x-data
                    x-on:input.debounce.350ms="$el.requestSubmit()"
                    class="mb-4 flex flex-col gap-2.5 sm:flex-row"
                >
                    <label class="relative min-w-0 flex-1" for="signatory-search">
                        <span class="sr-only">Search by name or position</span>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A8178]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" /></svg>
                        <input id="signatory-search" name="search" type="search" value="{{ $search }}" placeholder="Search by name or position" class="block w-full rounded-lg border-[#E7E2D8] bg-white py-2.5 pl-9 pr-3 text-sm text-[#201A15] shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:placeholder:text-slate-500">
                    </label>

                    <label class="sm:w-[210px]" for="signatory-role-filter">
                        <span class="sr-only">Filter by signature role</span>
                        <select id="signatory-role-filter" name="role" class="block w-full rounded-lg border-[#E7E2D8] bg-white px-3 py-2.5 text-sm text-[#201A15] shadow-sm focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                            <option value="">All roles</option>
                            @foreach ($roles as $key => $label)
                                <option value="{{ $key }}" @selected($selectedRole === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <button type="submit" class="sr-only">Apply filters</button>
                    @if ($search !== '' || $selectedRole !== '')
                        <a href="{{ route('signatories.index') }}" class="inline-flex items-center justify-center rounded-lg border border-[#E7E2D8] bg-white px-3 py-2.5 text-sm font-medium text-[#6B6258] transition hover:bg-[#F4F1EB] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">Clear</a>
                    @endif
                </form>

                <div class="space-y-4">
                    @forelse ($roles as $roleKey => $roleLabel)
                        @php($roleSignatories = $signatoryGroups->get($roleKey, collect()))
                        @continue($roleSignatories->isEmpty())

                        <section class="overflow-hidden rounded-xl border border-[#E7E2D8] bg-white shadow-[0_1px_2px_rgba(32,26,21,0.04),0_8px_20px_-12px_rgba(32,26,21,0.10)] dark:border-slate-700 dark:bg-slate-900" aria-labelledby="role-{{ $roleKey }}-heading">
                            <div class="flex items-center gap-2.5 px-[18px] py-3.5">
                                <span class="h-2 w-2 shrink-0 rounded-full bg-[#C1272D]" aria-hidden="true"></span>
                                <h3 id="role-{{ $roleKey }}-heading" class="min-w-0 flex-1 text-[14.5px] font-semibold text-[#201A15] dark:text-white">{{ $roleLabel }}</h3>
                                <span class="rounded-full bg-[#F4F1EB] px-2.5 py-0.5 text-[12.5px] text-[#6B6258] dark:bg-slate-800 dark:text-slate-300">{{ $roleSignatories->count() }}</span>
                            </div>

                            <div class="divide-y divide-[#E7E2D8] border-t border-[#E7E2D8] dark:divide-slate-700 dark:border-slate-700">
                                @foreach ($roleSignatories as $signatory)
                                    <article id="signatory-{{ $signatory->id }}" class="scroll-mt-40">
                                        @if ($editingSignatoryId === $signatory->id)
                                            <form action="{{ route('signatories.update', $signatory) }}" method="POST" class="bg-[#FBEAEA]/50 px-[18px] py-4 dark:bg-red-950/20">
                                                @csrf
                                                @method('PATCH')

                                                <div class="grid gap-2.5 sm:grid-cols-2">
                                                    <label class="text-[11.5px] font-medium text-[#6B6258] dark:text-slate-300" for="name-{{ $signatory->id }}">
                                                        Full name
                                                        <input id="name-{{ $signatory->id }}" name="name" value="{{ old('name', $signatory->name) }}" required maxlength="120" class="mt-1 block w-full rounded-md border-[#C1272D] bg-white px-2.5 py-2 text-[13.5px] text-[#201A15] focus:border-red-700 focus:ring-red-700 dark:bg-slate-950 dark:text-white">
                                                    </label>
                                                    <label class="text-[11.5px] font-medium text-[#6B6258] dark:text-slate-300" for="position-{{ $signatory->id }}">
                                                        Position / designation
                                                        <input id="position-{{ $signatory->id }}" name="position" value="{{ old('position', $signatory->position) }}" required maxlength="120" class="mt-1 block w-full rounded-md border-[#C1272D] bg-white px-2.5 py-2 text-[13.5px] text-[#201A15] focus:border-red-700 focus:ring-red-700 dark:bg-slate-950 dark:text-white">
                                                    </label>
                                                    <label class="text-[11.5px] font-medium text-[#6B6258] dark:text-slate-300" for="role-{{ $signatory->id }}">
                                                        Signature role
                                                        <select id="role-{{ $signatory->id }}" name="role_key" required class="mt-1 block w-full rounded-md border-[#E7E2D8] bg-white px-2.5 py-2 text-[13px] text-[#201A15] focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                                            @foreach ($roles as $key => $label)
                                                                <option value="{{ $key }}" @selected(old('role_key', $signatory->role_key) === $key)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </label>
                                                    <label class="text-[11.5px] font-medium text-[#6B6258] dark:text-slate-300" for="availability-{{ $signatory->id }}">
                                                        Availability
                                                        <select id="availability-{{ $signatory->id }}" name="active" required class="mt-1 block w-full rounded-md border-[#E7E2D8] bg-white px-2.5 py-2 text-[13px] text-[#201A15] focus:border-red-600 focus:ring-red-600 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                                            <option value="1" @selected((bool) old('active', $signatory->active))>Active</option>
                                                            <option value="0" @selected(! (bool) old('active', $signatory->active))>Inactive</option>
                                                        </select>
                                                    </label>
                                                </div>

                                                <div class="mt-3 flex justify-end gap-2">
                                                    <a href="{{ route('signatories.index', array_filter(['search' => $search, 'role' => $selectedRole])) }}#signatory-{{ $signatory->id }}" class="rounded-md border border-[#E7E2D8] bg-white px-3 py-2 text-xs font-medium text-[#6B6258] transition hover:bg-[#F4F1EB] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</a>
                                                    <button type="submit" class="rounded-md bg-[#C1272D] px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-[#9C1E23] focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 dark:focus:ring-offset-slate-900">Save changes</button>
                                                </div>
                                            </form>
                                        @else
                                            <div class="flex flex-col gap-3 px-[18px] py-[13px] sm:flex-row sm:items-center">
                                                <div class="flex min-w-0 flex-1 items-center gap-3">
                                                    <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-full bg-[#FBEAEA] text-[12.5px] font-bold uppercase text-[#C1272D]" aria-hidden="true">{{ \Illuminate\Support\Str::substr($signatory->name, 0, 1) }}</span>
                                                    <div class="min-w-0">
                                                        <h4 class="truncate text-[14.5px] font-semibold text-[#201A15] dark:text-white">{{ $signatory->name }}</h4>
                                                        <p class="mt-0.5 truncate text-[13px] text-[#6B6258] dark:text-slate-400">{{ $signatory->position }}</p>
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-1.5 pl-[46px] sm:pl-0">
                                                    <span class="mr-1 rounded-full px-3 py-1 text-[12px] font-semibold {{ $signatory->active ? 'bg-[#E8F3EC] text-[#3F7D5C] dark:bg-green-950/40 dark:text-green-300' : 'bg-[#F4F1EB] text-[#6B6258] dark:bg-slate-800 dark:text-slate-400' }}">{{ $signatory->active ? 'Active' : 'Inactive' }}</span>
                                                    <a href="{{ route('signatories.index', array_filter(['search' => $search, 'role' => $selectedRole, 'edit' => $signatory->id])) }}#signatory-{{ $signatory->id }}" title="Edit {{ $signatory->name }}" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-[#6B6258] transition hover:bg-[#F4F1EB] hover:text-[#201A15] focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
                                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931ZM19.5 7.125 16.875 4.5" /></svg>
                                                        Edit
                                                    </a>
                                                    <form
                                                        method="POST"
                                                        action="{{ route('signatories.destroy', $signatory) }}"
                                                        data-proposal-confirm
                                                        data-confirm-title="Remove {{ $signatory->name }}?"
                                                        data-confirm-text="This removes the name from future selections. Existing proposal signature blocks keep their saved copy."
                                                        data-confirm-button="Remove signatory"
                                                        data-confirm-icon="warning"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" title="Delete {{ $signatory->name }}" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-[#8A8178] transition hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 dark:text-slate-500 dark:hover:bg-red-950/40 dark:hover:text-red-300">
                                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.166L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.245-2.327L4.772 5.79m14.456 0A48.667 48.667 0 0 0 4.772 5.79" /></svg>
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @empty
                    @endforelse

                    @if ($signatoryGroups->isEmpty())
                        <div class="rounded-xl border border-dashed border-[#D7D0C6] bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
                            <h3 class="text-sm font-semibold text-[#201A15] dark:text-white">{{ $search !== '' || $selectedRole !== '' ? 'No matching signatories' : 'No signatories yet' }}</h3>
                            <p class="mt-1 text-[13px] text-[#6B6258] dark:text-slate-400">{{ $search !== '' || $selectedRole !== '' ? 'Try another search or clear the filters.' : 'Add the first approved name using the form.' }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
