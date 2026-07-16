<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-gray-900">Account Profile</h2>
                <p class="mt-1 text-xs text-gray-500">Your institutional identity, ATHENA access, and account activity.</p>
            </div>
            <a href="{{ route('dashboard') }}" class="inline-flex self-start items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-xs font-bold text-gray-700 transition hover:bg-gray-50">
                <span aria-hidden="true">&larr;</span> Back to workspace
            </a>
        </div>
    </x-slot>

    @php
        $roleDescriptions = [
            'faculty' => 'Submit proposals, review feedback, and browse research calls.',
            'faculty_researcher' => 'Access the research catalog and Research Support workspace.',
            'research_head' => 'Manage research calls, evaluate proposals, and issue final decisions.',
            'expert' => 'Review assigned proposals and submit subject-matter recommendations.',
        ];

        $statusClass = fn (string $status) => match ($status) {
            'approved' => 'bg-green-50 text-green-700',
            'rejected' => 'bg-red-50 text-red-700',
            'revision_requested' => 'bg-blue-50 text-blue-700',
            'resubmitted', 'expert_review' => 'bg-purple-50 text-purple-700',
            default => 'bg-amber-50 text-amber-700',
        };
    @endphp

    <div class="grid gap-6 xl:grid-cols-[340px_minmax(0,1fr)]">
        <aside class="space-y-6">
            <section class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm">
                <div class="relative h-28 bg-gradient-to-br from-red-700 via-red-600 to-red-900">
                    <div class="absolute inset-0 opacity-20 [background-image:linear-gradient(to_right,#fff_1px,transparent_1px),linear-gradient(to_bottom,#fff_1px,transparent_1px)] [background-size:20px_20px]"></div>
                </div>
                <div class="relative px-6 pb-6">
                    <div class="-mt-10 flex items-end justify-between gap-3">
                        @if ($user->avatar)
                            <img src="{{ $user->avatar }}" alt="{{ $user->name }}" class="h-20 w-20 rounded-2xl border-4 border-white bg-white object-cover shadow-md dark:border-slate-900 dark:bg-slate-900">
                        @else
                            <div class="flex h-20 w-20 items-center justify-center rounded-2xl border-4 border-white bg-slate-900 text-2xl font-black uppercase text-white shadow-md dark:border-slate-900">{{ substr($user->name, 0, 1) }}</div>
                        @endif
                        <span class="mb-1 inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-green-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Active
                        </span>
                    </div>

                    <h3 class="mt-4 text-xl font-black text-gray-900">{{ $user->name }}</h3>
                    <p class="mt-1 break-all text-sm text-gray-500">{{ $user->email }}</p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        @forelse ($user->getRoleNames() as $role)
                            <span class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-red-700">{{ str_replace('_', ' ', $role) }}</span>
                        @empty
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-500">No role assigned</span>
                        @endforelse
                    </div>

                    <dl class="mt-6 space-y-4 border-t border-gray-100 pt-5">
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Authentication</dt>
                            <dd class="mt-1 flex items-center gap-2 text-sm font-bold text-gray-700">
                                <svg class="h-4 w-4 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                BatStateU Google Workspace
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">Google connection</dt>
                            <dd class="mt-1 text-sm font-bold {{ $user->google_id ? 'text-green-700' : 'text-amber-700' }}">{{ $user->google_id ? 'Connected' : 'Pending first Google sign-in' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] font-black uppercase tracking-wider text-gray-400">ATHENA member since</dt>
                            <dd class="mt-1 text-sm font-bold text-gray-700">{{ $user->created_at->format('F Y') }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="text-xs font-black uppercase tracking-wider text-gray-400">Institutional account</h3>
                <p class="mt-3 text-xs leading-5 text-gray-600">Your name, email, password, and account recovery are managed by Batangas State University. Google profile changes synchronize the next time you sign in.</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl border border-red-200 px-4 py-2.5 text-xs font-bold text-red-600 transition hover:bg-red-50">Sign out securely</button>
                </form>
            </section>
        </aside>

        <div class="space-y-6">
            <section>
                <div class="mb-3">
                    <h3 class="text-sm font-black text-gray-900">Account activity</h3>
                    <p class="mt-0.5 text-xs text-gray-500">Records associated with your current ATHENA roles.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-gray-400">Proposals</p><p class="mt-2 text-3xl font-black text-gray-900">{{ $user->proposals_count }}</p></div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-gray-400">Approved</p><p class="mt-2 text-3xl font-black text-green-700">{{ $user->approved_proposals_count }}</p></div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-gray-400">In progress</p><p class="mt-2 text-3xl font-black text-blue-700">{{ $user->active_proposals_count }}</p></div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"><p class="text-[10px] font-black uppercase tracking-wider text-gray-400">Reviews</p><p class="mt-2 text-3xl font-black text-purple-700">{{ $user->topic_reviews_count + $user->expert_assignments_count }}</p></div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h3 class="text-sm font-black text-gray-900">Access and permissions</h3>
                    <p class="mt-0.5 text-xs text-gray-500">What your assigned roles allow you to do.</p>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse ($user->getRoleNames() as $role)
                        <div class="flex gap-3 p-5">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <div><p class="text-sm font-black capitalize text-gray-800">{{ str_replace('_', ' ', $role) }}</p><p class="mt-1 text-xs leading-5 text-gray-500">{{ $roleDescriptions[$role] ?? 'Role-specific ATHENA access.' }}</p></div>
                        </div>
                    @empty
                        <div class="p-6 text-center text-xs text-gray-500">No role has been assigned. Contact the Research Office for access.</div>
                    @endforelse
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div><h3 class="text-sm font-black text-gray-900">Recent proposals</h3><p class="mt-0.5 text-xs text-gray-500">Your latest research submissions.</p></div>
                    @if ($user->isUsingWorkspace('faculty_researcher'))<a href="{{ route('research.index') }}" class="text-xs font-bold text-red-600 hover:text-red-700">View all</a>@endif
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse ($recentProposals as $topic)
                        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0"><p class="truncate text-sm font-black text-gray-800">{{ $topic->title }}</p><p class="mt-1 text-xs text-gray-500">{{ $topic->researchCall->title }}@if ($topic->category) · {{ $topic->category->name }}@endif</p></div>
                            <span class="self-start whitespace-nowrap rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $statusClass($topic->status) }}">{{ str_replace('_', ' ', $topic->status) }}</span>
                        </div>
                    @empty
                        <div class="p-8 text-center"><p class="text-sm font-bold text-gray-700">No proposals yet</p><p class="mt-1 text-xs text-gray-500">Research submissions associated with your account will appear here.</p></div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
