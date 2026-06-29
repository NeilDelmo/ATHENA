<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-black text-2xl text-gray-900 tracking-tight">
                Research Head Dashboard
            </h2>
            <p class="text-xs text-gray-500 mt-1">
                Review incoming faculty proposals and update their evaluation status.
            </p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="rounded-2xl border border-gray-200/60 bg-white p-6 shadow-sm">
                <span class="block text-xs font-bold uppercase tracking-wider text-gray-400">Incoming Proposals</span>
                <span class="mt-1 block text-3xl font-black text-gray-900">{{ $topics->count() }}</span>
            </div>
            <div class="rounded-2xl border border-gray-200/60 bg-white p-6 shadow-sm">
                <span class="block text-xs font-bold uppercase tracking-wider text-gray-400">Pending Review</span>
                <span class="mt-1 block text-3xl font-black text-gray-900">{{ $topics->where('status', 'pending')->count() }}</span>
            </div>
            <div class="rounded-2xl border border-gray-200/60 bg-white p-6 shadow-sm">
                <span class="block text-xs font-bold uppercase tracking-wider text-gray-400">Approved</span>
                <span class="mt-1 block text-3xl font-black text-gray-900">{{ $topics->where('status', 'approved')->count() }}</span>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200/60 bg-white shadow-sm">
            <div class="border-b border-gray-100 p-6">
                <h3 class="text-base font-bold text-gray-900">Incoming Topic Proposals</h3>
                <p class="mt-0.5 text-xs text-gray-400">Faculty submissions appear here after upload.</p>
            </div>

            @forelse ($topics as $topic)
                <div class="grid gap-4 border-b border-gray-100 p-5 last:border-b-0 lg:grid-cols-[1fr_auto] lg:items-center">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-sm font-bold text-gray-900">{{ $topic->title }}</h4>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider
                                {{ $topic->status === 'approved' ? 'bg-green-50 text-green-700' : '' }}
                                {{ $topic->status === 'rejected' ? 'bg-red-50 text-red-700' : '' }}
                                {{ $topic->status === 'pending' ? 'bg-amber-50 text-amber-700' : '' }}">
                                {{ str_replace('_', ' ', $topic->status) }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ $topic->description ?: 'No description provided.' }}</p>
                        <p class="mt-2 text-[11px] font-medium text-gray-400">
                            Submitted by {{ $topic->user->name }} on {{ $topic->created_at->format('M d, Y h:i A') }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                        <a href="{{ route('topics.download', $topic) }}" class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 transition hover:bg-gray-50">
                            Download
                        </a>

                        <form action="{{ route('research_head.topics.updateStatus', $topic) }}" method="POST" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <select name="status" class="rounded-xl border-gray-200 text-xs font-bold text-gray-700 shadow-sm focus:border-red-600 focus:ring-red-600">
                                <option value="pending" @selected($topic->status === 'pending')>Pending</option>
                                <option value="approved" @selected($topic->status === 'approved')>Approved</option>
                                <option value="rejected" @selected($topic->status === 'rejected')>Rejected</option>
                            </select>
                            <button type="submit" class="rounded-xl bg-red-600 px-3 py-2 text-xs font-bold text-white transition hover:bg-red-700">
                                Update
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="p-12 text-center">
                    <h4 class="text-sm font-bold text-gray-800">No proposals submitted yet</h4>
                    <p class="mt-1 text-xs text-gray-400">Faculty proposal uploads will appear here.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
