<x-app-layout>
    @php
        $statusClass = match ($topic->status) {
            'approved' => 'bg-green-50 text-green-700',
            'rejected' => 'bg-red-50 text-red-700',
            'revision_requested' => 'bg-blue-50 text-blue-700',
            'resubmitted', 'expert_review' => 'bg-purple-50 text-purple-700',
            default => 'bg-amber-50 text-amber-700',
        };
        $backRoute = Auth::user()->hasRole('research_head')
            ? route('research_head.dashboard')
            : (Auth::user()->hasRole('expert') ? route('expert.dashboard') : route('faculty.dashboard'));
        $completedDocuments = $packageChecklist->where('status', 'complete')->count();
        $packageComplete = $completedDocuments === $packageChecklist->count();
        $canDecide = Auth::user()->hasRole('research_head') && in_array($topic->status, ['pending', 'resubmitted', 'for_final_decision'], true);
    @endphp

    <x-slot name="header">
        <div class="space-y-3">
            <a href="{{ $backRoute }}" class="inline-flex items-center gap-1 text-xs font-bold text-gray-500 transition hover:text-red-600">&larr; Back to dashboard</a>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <h2 class="text-2xl font-black tracking-tight text-gray-900">{{ $topic->title }}</h2>
                    <p class="mt-1 text-xs text-gray-500">Proposal #{{ $topic->id }} · {{ $topic->user->name }} · {{ $topic->researchCall->title }}</p>
                </div>
                <span class="self-start rounded-full px-3 py-1.5 text-[11px] font-black uppercase tracking-wider {{ $statusClass }}">{{ str_replace('_', ' ', $topic->status) }}</span>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><p class="font-bold">The action could not be completed.</p><p class="mt-1 text-xs">{{ $errors->first() }}</p></div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="space-y-6">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><h3 class="text-sm font-black text-gray-900">Proposal package checklist</h3><p class="mt-1 text-xs text-gray-500">Latest version: {{ $latestVersion ? 'Version '.$latestVersion->version_number : 'No version available' }}</p></div>
                        <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase {{ $packageComplete ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' }}">{{ $completedDocuments }}/{{ $packageChecklist->count() }} complete</span>
                    </div>

                    @unless ($packageComplete)
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800">This package is incomplete or references a missing stored file. It should not receive final approval until every item is available.</div>
                    @endunless

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($packageChecklist as $item)
                            <div class="flex items-center gap-3 rounded-xl border border-gray-200 p-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $item['status'] === 'complete' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                                    {{ $item['status'] === 'complete' ? '✓' : '!' }}
                                </span>
                                <div class="min-w-0"><p class="text-xs font-black text-gray-800">{{ $item['label'] }}</p><p class="mt-0.5 text-[11px] text-gray-400">{{ $item['status'] === 'complete' ? $item['count'].' file(s) available' : ($item['status'] === 'missing' ? 'Not included' : 'Stored file is unavailable') }}</p></div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4"><h3 class="text-sm font-black text-gray-900">Version comparison</h3><p class="mt-1 text-xs text-gray-500">Metadata and file changes in the latest revision.</p></div>
                    @if ($previousVersion && $latestVersion)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-left text-xs">
                                <thead class="bg-gray-50 text-[10px] font-black uppercase tracking-wider text-gray-400"><tr><th class="px-5 py-3">Field</th><th class="px-5 py-3">Version {{ $previousVersion->version_number }}</th><th class="px-5 py-3">Version {{ $latestVersion->version_number }}</th></tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($comparisonRows as $row)
                                        <tr class="{{ $row['changed'] ? 'bg-amber-50/50' : '' }}"><th class="px-5 py-3 font-black text-gray-700">{{ $row['label'] }} @if ($row['changed'])<span class="ml-1 text-[9px] uppercase text-amber-700">Changed</span>@endif</th><td class="max-w-xs px-5 py-3 text-gray-500">{{ $row['previous'] }}</td><td class="max-w-xs px-5 py-3 font-semibold text-gray-700">{{ $row['latest'] }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="border-t border-gray-100 p-5">
                            <p class="text-[10px] font-black uppercase tracking-wider text-gray-400">Files changed in version {{ $latestVersion->version_number }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @forelse ($latestVersion->files->where('is_carried_forward', false) as $file)
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-700">{{ $file->label() }}</span>
                                @empty
                                    <span class="text-xs text-gray-400">No package files were replaced.</span>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <div class="p-8 text-center"><p class="text-sm font-bold text-gray-700">Initial version</p><p class="mt-1 text-xs text-gray-400">A comparison will appear after the first revision.</p></div>
                    @endif
                </section>

                @include('topics.partials.version-history', ['topic' => $topic, 'expanded' => true])

                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-6 py-4"><h3 class="text-sm font-black text-gray-900">Review and decision timeline</h3><p class="mt-1 text-xs text-gray-500">Research Head decisions and subject-expert recommendations.</p></div>
                    <div class="space-y-5 p-6">
                        @forelse ($topic->reviews as $review)
                            <div class="border-l-2 border-red-200 pl-4"><div class="flex flex-wrap justify-between gap-2"><p class="text-xs font-black uppercase text-gray-700">{{ str_replace('_', ' ', $review->decision) }}</p><time class="text-[11px] text-gray-400">{{ $review->created_at->format('M d, Y h:i A') }}</time></div><p class="mt-1 text-[11px] font-semibold text-gray-400">{{ $review->reviewer?->name ?? 'Former Research Head' }}</p>@if ($review->comment)<p class="mt-2 whitespace-pre-line rounded-xl bg-gray-50 p-3 text-xs leading-5 text-gray-600">{{ $review->comment }}</p>@endif</div>
                        @empty
                            <p class="text-center text-xs text-gray-400">No Research Head decision has been recorded.</p>
                        @endforelse

                        @foreach ($topic->expertAssignments as $assignment)
                            <div class="border-l-2 border-purple-200 pl-4"><div class="flex flex-wrap justify-between gap-2"><p class="text-xs font-black uppercase text-purple-700">Expert review · {{ str_replace('_', ' ', $assignment->status) }}</p><time class="text-[11px] text-gray-400">{{ ($assignment->reviewed_at ?: $assignment->created_at)->format('M d, Y h:i A') }}</time></div><p class="mt-1 text-[11px] font-semibold text-gray-400">{{ $assignment->expert->name }}</p>@if ($assignment->recommendation)<p class="mt-2 text-[11px] font-black uppercase text-purple-700">{{ str_replace('_', ' ', $assignment->recommendation) }}</p><p class="mt-1 whitespace-pre-line rounded-xl bg-purple-50/50 p-3 text-xs leading-5 text-gray-600">{{ $assignment->comment }}</p>@endif</div>
                        @endforeach
                    </div>
                </section>
            </div>

            <aside class="space-y-5">
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                    <h3 class="text-xs font-black uppercase tracking-wider text-gray-400">Research details</h3>
                    <dl class="mt-4 space-y-4"><div><dt class="text-[11px] font-bold uppercase text-gray-400">Total project cost</dt><dd class="mt-1 text-lg font-black text-gray-900">PHP {{ number_format((float) $topic->estimated_budget, 2) }}</dd></div><div class="border-t border-gray-100 pt-3"><dt class="text-[11px] font-bold uppercase text-gray-400">Duration</dt><dd class="mt-1 text-sm font-bold text-gray-700">{{ $topic->estimated_duration_months }} months</dd></div><div class="border-t border-gray-100 pt-3"><dt class="text-[11px] font-bold uppercase text-gray-400">Category</dt><dd class="mt-1 text-sm font-bold text-gray-700">{{ $topic->category->name }}</dd></div></dl>
                    <p class="mt-4 whitespace-pre-line border-t border-gray-100 pt-4 text-xs leading-5 text-gray-500">{{ $topic->description ?: 'No proposal summary provided.' }}</p>
                </section>

                @if ($canDecide)
                    <form action="{{ route('research_head.topics.updateStatus', $topic) }}" method="POST" enctype="multipart/form-data" class="space-y-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        @csrf @method('PATCH')
                        <input type="hidden" name="redirect_to" value="topic">
                        <h3 class="text-sm font-black text-gray-900">Research Head action</h3>
                        <select name="status" required class="block w-full rounded-xl border-gray-200 text-xs font-bold"><option value="">Choose an action</option>@if ($topic->status !== 'for_final_decision')<option value="expert_review">Send to subject experts</option>@endif<option value="approved">Approve and sign</option><option value="revision_requested">Request revision</option><option value="rejected">Reject proposal</option></select>
                        <div><label class="text-[11px] font-bold text-gray-500">Subject experts</label><select name="expert_ids[]" multiple size="{{ min(max($experts->count(), 2), 5) }}" class="mt-1 block w-full rounded-xl border-gray-200 text-xs">@foreach ($experts as $expert)<option value="{{ $expert->id }}">{{ $expert->name }} - {{ $expert->email }}</option>@endforeach</select></div>
                        <div><label class="text-[11px] font-bold text-gray-500">Signed approval PDF</label><input type="file" name="signed_approval" accept=".pdf" class="mt-1 block w-full rounded-xl border border-gray-200 p-2 text-xs"></div>
                        <textarea name="comment" rows="4" maxlength="5000" placeholder="Decision rationale (required for revision or rejection)" class="block w-full rounded-xl border-gray-200 text-xs"></textarea>
                        <button class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-xs font-bold text-white">Submit action</button>
                    </form>
                @elseif (Auth::user()->hasRole('research_head'))
                    <div class="rounded-2xl bg-gray-100 p-5 text-center text-xs font-bold text-gray-600">No Research Head action is available while this proposal is {{ str_replace('_', ' ', $topic->status) }}.</div>
                @endif

                @if ($expertAssignment)
                    <section class="rounded-2xl border border-purple-200 bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-black text-gray-900">Expert recommendation</h3>
                        @if ($expertAssignment->status === 'pending')
                            <form method="POST" action="{{ route('expert.assignments.submit', $expertAssignment) }}" class="mt-3 space-y-3">@csrf @method('PATCH')<input type="hidden" name="redirect_to" value="topic"><select name="recommendation" required class="block w-full rounded-xl border-gray-200 text-xs font-bold"><option value="">Choose recommendation</option><option value="recommend_approval">Recommend approval</option><option value="recommend_revision">Recommend revision</option><option value="recommend_rejection">Recommend rejection</option></select><textarea name="comment" rows="5" required maxlength="5000" placeholder="Explain your assessment." class="block w-full rounded-xl border-gray-200 text-xs"></textarea><button class="w-full rounded-xl bg-purple-700 px-4 py-2.5 text-xs font-bold text-white">Submit recommendation</button></form>
                        @else
                            <p class="mt-3 text-xs font-black uppercase text-purple-700">{{ str_replace('_', ' ', $expertAssignment->recommendation) }}</p><p class="mt-2 whitespace-pre-line text-xs leading-5 text-gray-600">{{ $expertAssignment->comment }}</p>
                        @endif
                    </section>
                @endif

                @if ($topic->status === 'revision_requested' && $topic->user_id === Auth::id())
                    <form action="{{ route('faculty.topics.resubmit', $topic) }}" method="POST" enctype="multipart/form-data" class="space-y-3 rounded-2xl border border-blue-200 bg-white p-5 shadow-sm">
                        @csrf @method('PATCH')
                        <input type="hidden" name="redirect_to" value="topic">
                        <h3 class="text-sm font-black text-gray-900">Submit revision</h3>
                        <p class="text-xs leading-5 text-gray-500">Update the metadata and upload only changed files. Unchanged files carry forward.</p>
                        <input name="title" value="{{ old('title', $topic->title) }}" required class="block w-full rounded-xl border-gray-200 text-xs" placeholder="Project title">
                        <textarea name="description" rows="3" class="block w-full rounded-xl border-gray-200 text-xs" placeholder="Description">{{ old('description', $topic->description) }}</textarea>
                        <input name="estimated_budget" type="number" min="0" max="9999999999.99" step="0.01" value="{{ old('estimated_budget', $topic->estimated_budget) }}" required class="block w-full rounded-xl border-gray-200 text-xs" placeholder="Total project cost">
                        <input name="estimated_duration_months" type="number" min="1" max="120" value="{{ old('estimated_duration_months', $topic->estimated_duration_months) }}" required class="block w-full rounded-xl border-gray-200 text-xs" placeholder="Duration in months">
                        <textarea name="change_summary" rows="2" maxlength="2000" class="block w-full rounded-xl border-gray-200 text-xs" placeholder="What changed in this version?"></textarea>
                        @foreach ([['detailed_proposal', 'Detailed proposal', '.doc,.docx,.pdf'], ['work_plan', 'Work plan', '.doc,.docx,.pdf'], ['line_item_budget', 'Line-item budget', '.doc,.docx,.pdf'], ['expense_breakdown', 'Expense breakdown', '.xls,.xlsx']] as [$name, $label, $accept])
                            <label class="block text-[11px] font-bold text-gray-500">{{ $label }}<input name="{{ $name }}" type="file" accept="{{ $accept }}" class="mt-1 block w-full rounded-xl border border-gray-200 p-2 text-xs"></label>
                        @endforeach
                        <label class="block text-[11px] font-bold text-gray-500">Curriculum vitae files<input name="curricula_vitae[]" type="file" accept=".doc,.docx,.pdf" multiple class="mt-1 block w-full rounded-xl border border-gray-200 p-2 text-xs"></label>
                        <button class="w-full rounded-xl bg-blue-700 px-4 py-2.5 text-xs font-bold text-white">Submit new version</button>
                    </form>
                @endif

                @if ($topic->signed_approval_path)
                    <a href="{{ route('topics.approval', $topic) }}" class="flex justify-center rounded-xl bg-green-700 px-4 py-3 text-xs font-bold text-white">Download signed approval</a>
                @endif
            </aside>
        </div>
    </div>
</x-app-layout>
