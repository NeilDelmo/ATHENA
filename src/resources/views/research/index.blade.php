<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-black tracking-tight text-gray-900">Research</h2>
            <p class="mt-1 text-xs text-gray-500">Browse your proposals and track each review status.</p>
        </div>
    </x-slot>

    <div class="space-y-5">
        <form method="GET" action="{{ route('research.index') }}" class="grid gap-3 rounded-2xl border border-gray-200/60 bg-white p-4 shadow-sm sm:grid-cols-[1fr_220px_auto]">
            <div>
                <label for="research_search" class="sr-only">Search research</label>
                <input id="research_search" name="search" type="search" value="{{ $search }}" placeholder="Search title or description..." class="block w-full rounded-xl border-gray-200 text-sm shadow-sm focus:border-red-600 focus:ring-red-600">
            </div>
            <div>
                <label for="research_status" class="sr-only">Filter by status</label>
                <select id="research_status" name="status" class="block w-full rounded-xl border-gray-200 text-sm font-semibold text-gray-700 shadow-sm focus:border-red-600 focus:ring-red-600">
                    <option value="">All statuses</option>
                    <option value="pending" @selected($status === 'pending')>Pending</option>
                    <option value="expert_review" @selected($status === 'expert_review')>Initial screening</option>
                    <option value="for_final_decision" @selected($status === 'for_final_decision')>Screening complete</option>
                    <option value="revision_requested" @selected($status === 'revision_requested')>Revision requested</option>
                    <option value="resubmitted" @selected($status === 'resubmitted')>Resubmitted</option>
                    <option value="approved" @selected($status === 'approved')>Approved</option>
                    <option value="rejected" @selected($status === 'rejected')>Rejected</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-red-700">Filter</button>
                @if ($search !== '' || $status !== '')
                    <a href="{{ route('research.index') }}" class="inline-flex items-center rounded-xl border border-gray-200 px-4 py-2 text-xs font-bold text-gray-600 transition hover:bg-gray-50">Clear</a>
                @endif
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-gray-200/60 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Research list</h3>
                    <p class="mt-0.5 text-xs text-gray-400">{{ $topics->total() }} {{ Str::plural('record', $topics->total()) }} found</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/70">
                        <tr>
                            <th class="px-5 py-3 text-left text-[11px] font-black uppercase tracking-wider text-gray-400">Research</th>
                            <th class="px-5 py-3 text-left text-[11px] font-black uppercase tracking-wider text-gray-400">Status</th>
                            <th class="px-5 py-3 text-right text-[11px] font-black uppercase tracking-wider text-gray-400">Budget</th>
                            <th class="px-5 py-3 text-left text-[11px] font-black uppercase tracking-wider text-gray-400">Updated</th>
                            <th class="px-5 py-3"><span class="sr-only">View</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($topics as $topic)
                            @php
                                $statusClass = match ($topic->status) {
                                    'approved' => 'bg-green-50 text-green-700',
                                    'rejected' => 'bg-red-50 text-red-700',
                                    'revision_requested' => 'bg-blue-50 text-blue-700',
                                    'resubmitted' => 'bg-purple-50 text-purple-700',
                                    default => 'bg-amber-50 text-amber-700',
                                };
                            @endphp
                            <tr class="transition hover:bg-gray-50/70">
                                <td class="px-5 py-4">
                                    <a href="{{ route('topics.show', $topic) }}" class="block max-w-md">
                                        <span class="block text-sm font-bold text-gray-900 hover:text-red-600">{{ $topic->title }}</span>
                                        <span class="mt-1 block truncate text-xs text-gray-400">{{ $topic->description ?: 'No description provided.' }}</span>
                                        <span class="mt-1 block text-[11px] font-semibold text-gray-400">{{ $topic->researchCall->title }}@if ($topic->category) · {{ $topic->category->name }}@endif</span>
                                    </a>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase tracking-wider {{ $statusClass }}">{{ str_replace('_', ' ', $topic->status) }}</span>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-right text-xs font-bold text-gray-700">
                                    {{ $topic->estimated_budget !== null ? 'PHP '.number_format((float) $topic->estimated_budget, 2) : 'Not provided' }}
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-xs text-gray-500">{{ $topic->updated_at->format('M d, Y') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right">
                                    <a href="{{ route('topics.show', $topic) }}{{ $topic->status === 'approved' ? '#project-monitoring' : '' }}" class="inline-flex items-center rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700">{{ $topic->status === 'approved' ? 'Monitor project' : 'View details' }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-14 text-center">
                                    <p class="text-sm font-bold text-gray-700">No research found</p>
                                    <p class="mt-1 text-xs text-gray-400">Try changing the search or status filter.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($topics->hasPages())
                <div class="border-t border-gray-100 px-5 py-4">{{ $topics->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
