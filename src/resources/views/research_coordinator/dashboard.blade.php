<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-black tracking-tight text-gray-900 dark:text-white">Research Coordinator Dashboard</h2>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">View faculty members assigned to your college.</p>
        </div>
    </x-slot>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-2 border-b border-gray-100 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-black text-gray-900 dark:text-white">Faculty Members</h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">{{ $coordinator->college ?: 'No college selected' }}</p>
            </div>
            <span class="text-xs font-bold text-gray-500 dark:text-slate-400">{{ $members->total() }} {{ Str::plural('member', $members->total()) }}</span>
        </div>

        @if (! $coordinator->college)
            <div class="border-b border-amber-200 bg-amber-50 px-5 py-4 text-sm font-semibold text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300">
                Set your college in your account profile to view your faculty members.
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-slate-800">
                <thead class="bg-gray-50 dark:bg-slate-950/50">
                    
                    <tr>
                        <th scope="col" class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Member</th>
                        <th scope="col" class="px-5 py-3 text-left text-[10px] font-black uppercase tracking-wider text-gray-500 dark:text-slate-400">Email</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-800">
                    @forelse ($members as $member)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-slate-800/60">
                            <td class="whitespace-nowrap px-5 py-4">
                                <div class="flex items-center gap-3">
                                    @if ($member->avatar)
                                        <img src="{{ $member->avatar }}" alt="" class="h-10 w-10 rounded-xl bg-gray-100 object-cover dark:bg-slate-800">
                                    @else
                                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-sm font-black uppercase text-red-700 dark:bg-red-950/50 dark:text-red-300">{{ Str::substr($member->name, 0, 1) }}</div>
                                    @endif
                                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $member->name }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-slate-300">{{ $member->email }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-5 py-12 text-center">
                                <p class="text-sm font-bold text-gray-700 dark:text-slate-200">No faculty members found</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Members from your college will appear here.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($members->hasPages())
            <div class="border-t border-gray-100 px-5 py-4 dark:border-slate-800">{{ $members->links() }}</div>
        @endif
    </section>
</x-app-layout>
