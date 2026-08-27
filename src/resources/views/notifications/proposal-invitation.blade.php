<x-app-layout>
    <div class="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="border-b border-gray-100 bg-red-50 px-6 py-5 dark:border-slate-800 dark:bg-red-950/30 sm:px-8">
                <p class="text-xs font-black uppercase tracking-[0.2em] text-red-700 dark:text-red-300">Proposal workspace invitation</p>
                <h1 class="mt-2 text-2xl font-black text-gray-900 dark:text-white">Join as a collaborator</h1>
            </div>

            <div class="px-6 py-7 sm:px-8">
                <p class="text-sm leading-6 text-gray-600 dark:text-slate-300">
                    <span class="font-bold text-gray-900 dark:text-white">{{ $proposalDraftMember->draft->owner->name }}</span>
                    invited you to collaborate on
                    <span class="font-bold text-gray-900 dark:text-white">“{{ $proposalDraftMember->draft->project_title }}”</span>.
                </p>
                <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-slate-300">
                    Collaborators can edit proposal papers. Only the proposal owner can manage invitations, submit, or delete the proposal.
                </p>

                @if ($workloadWarning)
                    <x-proposal-alert type="warning" class="mt-5">{{ $workloadWarning }}</x-proposal-alert>
                @endif

                <form method="POST" action="{{ route('notifications.proposal-invitations.accept', $proposalDraftMember) }}" class="mt-7 flex flex-wrap gap-3">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-5 py-3 text-sm font-black text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                        Accept invitation
                    </button>
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-5 py-3 text-sm font-bold text-gray-700 transition hover:bg-gray-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                        Not now
                    </a>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
