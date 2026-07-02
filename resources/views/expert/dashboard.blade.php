<x-app-layout>
    <x-slot name="header"><div><h2 class="text-2xl font-black tracking-tight text-gray-900">Expert Review Workspace</h2><p class="mt-1 text-xs text-gray-500">Evaluate proposals assigned to your area of expertise.</p></div></x-slot>
    <div class="space-y-5">
        @if (session('success'))<div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">{{ session('success') }}</div>@endif
        @forelse ($assignments as $assignment)
            <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:justify-between">
                    <div class="max-w-3xl"><div class="flex flex-wrap gap-2"><h3 class="text-sm font-black text-gray-900">{{ $assignment->topic->title }}</h3><span class="rounded-full px-2 py-1 text-[10px] font-black uppercase {{ $assignment->status === 'completed' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">{{ $assignment->status }}</span></div><p class="mt-1 text-xs font-semibold text-gray-400">{{ $assignment->topic->category->name }} · {{ $assignment->topic->researchCall->title }} · {{ $assignment->topic->user->name }}</p><p class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-600">{{ $assignment->topic->description ?: 'No proposal summary provided.' }}</p><div class="mt-3 flex gap-4 text-xs font-bold text-gray-500"><span>Total cost: PHP {{ number_format((float) $assignment->topic->estimated_budget, 2) }}</span><span>Duration: {{ $assignment->topic->estimated_duration_months }} months</span></div><div class="mt-4 flex gap-2"><a href="{{ route('topics.show', $assignment->topic) }}" class="inline-flex rounded-xl bg-gray-900 px-3 py-2 text-xs font-bold text-white">Open review workspace</a><a href="{{ route('topics.download', $assignment->topic) }}" class="inline-flex rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700">Download proposal</a></div></div>
                    <div class="lg:w-80">
                        @if ($assignment->status === 'pending')
                            <form method="POST" action="{{ route('expert.assignments.submit', $assignment) }}" class="space-y-3 rounded-xl bg-gray-50 p-4">@csrf @method('PATCH')<select name="recommendation" required class="block w-full rounded-xl border-gray-200 text-xs font-bold"><option value="">Choose recommendation</option><option value="recommend_approval">Recommend approval</option><option value="recommend_revision">Recommend revision</option><option value="recommend_rejection">Recommend rejection</option></select><textarea name="comment" rows="5" required maxlength="5000" placeholder="Explain whether the project is needed, feasible, and appropriate for this category." class="block w-full rounded-xl border-gray-200 text-xs"></textarea><button class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-xs font-bold text-white">Submit recommendation</button></form>
                        @else
                            <div class="rounded-xl bg-green-50 p-4"><p class="text-xs font-black uppercase text-green-700">{{ str_replace('_', ' ', $assignment->recommendation) }}</p><p class="mt-2 whitespace-pre-line text-xs leading-5 text-green-900">{{ $assignment->comment }}</p><p class="mt-2 text-[11px] text-green-600">Submitted {{ $assignment->reviewed_at?->format('M d, Y h:i A') }}</p></div>
                        @endif
                    </div>
                </div>
                @include('topics.partials.version-history', ['topic' => $assignment->topic])
            </article>
        @empty
            <div class="rounded-2xl border border-gray-200 bg-white p-12 text-center"><p class="text-sm font-bold text-gray-700">No expert reviews assigned</p><p class="mt-1 text-xs text-gray-400">New assignments from the Research Head will appear here.</p></div>
        @endforelse
    </div>
</x-app-layout>
