<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="font-black text-2xl text-gray-900 tracking-tight">
                    Faculty Research Workspace
                </h2>
                <p class="text-xs text-gray-500 mt-1">
                    Welcome back, <span class="font-semibold text-red-600">{{ Auth::user()->name }}</span>. Manage and track your institutional research submissions.
                </p>
            </div>

            <div>
                <button id="openSubmitModalBtn" @disabled($activeCalls->isEmpty()) class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 disabled:bg-gray-300 disabled:cursor-not-allowed text-white font-bold text-xs px-4 py-2.5 rounded-xl transition duration-150 shadow-sm shadow-red-600/10 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    {{ $activeCalls->isEmpty() ? 'No Open Research Call' : 'Submit Proposal' }}
                </button>
            </div>
        </div>
    </x-slot>

    <div class="space-y-8">
        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->submission->any() || $errors->resubmission->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-bold">Please review your submission.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach (array_merge($errors->submission->all(), $errors->resubmission->all()) as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        
        <div class="relative w-full h-56 sm:h-64 rounded-2xl overflow-hidden shadow-sm border border-gray-200/60 bg-gray-900 group">
            
            <div class="relative w-full h-full">
                <div class="slide-item absolute inset-0 w-full h-full transition-all duration-700 opacity-100 scale-100">
                    <img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=1200&auto=format&fit=crop" class="w-full h-full object-cover object-center opacity-40 group-hover:scale-102 transition duration-700" alt="Banner Image">
                    <div class="absolute inset-0 bg-gradient-to-t from-gray-950 via-gray-900/40 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 w-full p-6 sm:p-8 text-white z-10">
                        <span class="text-[10px] bg-red-600 text-white px-2 py-0.5 rounded font-black tracking-widest uppercase mb-2 inline-block">Institutional Notice</span>
                        <h3 class="text-lg sm:text-xl font-black tracking-tight text-white">Call for Proposals Open</h3>
                        <p class="text-xs text-gray-300 mt-1 max-w-xl">Submit your institutional research projects for Fiscal Year 2026.</p>
                    </div>
                </div>

                <div class="slide-item absolute inset-0 w-full h-full transition-all duration-700 opacity-0 scale-105 pointer-events-none">
                    <img src="https://images.unsplash.com/photo-1434030216411-0b793f4b4173?q=80&w=1200&auto=format&fit=crop" class="w-full h-full object-cover object-center opacity-40 group-hover:scale-102 transition duration-700" alt="Banner Image">
                    <div class="absolute inset-0 bg-gradient-to-t from-gray-950 via-gray-900/40 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 w-full p-6 sm:p-8 text-white z-10">
                        <span class="text-[10px] bg-red-600 text-white px-2 py-0.5 rounded font-black tracking-widest uppercase mb-2 inline-block">Institutional Notice</span>
                        <h3 class="text-lg sm:text-xl font-black tracking-tight text-white">Research Ethics Review</h3>
                        <p class="text-xs text-gray-300 mt-1 max-w-xl">Ensure compliance with the newly updated BatStateU IRB guidelines.</p>
                    </div>
                </div>

                <div class="slide-item absolute inset-0 w-full h-full transition-all duration-700 opacity-0 scale-105 pointer-events-none">
                    <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=1200&auto=format&fit=crop" class="w-full h-full object-cover object-center opacity-40 group-hover:scale-102 transition duration-700" alt="Banner Image">
                    <div class="absolute inset-0 bg-gradient-to-t from-gray-950 via-gray-900/40 to-transparent"></div>
                    <div class="absolute bottom-0 left-0 w-full p-6 sm:p-8 text-white z-10">
                        <span class="text-[10px] bg-red-600 text-white px-2 py-0.5 rounded font-black tracking-widest uppercase mb-2 inline-block">Institutional Notice</span>
                        <h3 class="text-lg sm:text-xl font-black tracking-tight text-white">Faculty Writing Workshop</h3>
                        <p class="text-xs text-gray-300 mt-1 max-w-xl">Join our technical writing mentorship camp this coming month.</p>
                    </div>
                </div>
            </div>

            <div class="absolute bottom-4 right-6 flex items-center gap-1.5 z-20">
                <button class="slide-dot h-1.5 w-5 bg-red-600 rounded-full transition-all duration-300 cursor-pointer focus:outline-none"></button>
                <button class="slide-dot h-1.5 w-1.5 bg-white/40 hover:bg-white/70 rounded-full transition-all duration-300 cursor-pointer focus:outline-none"></button>
                <button class="slide-dot h-1.5 w-1.5 bg-white/40 hover:bg-white/70 rounded-full transition-all duration-300 cursor-pointer focus:outline-none"></button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="bg-white p-6 rounded-2xl border border-gray-200/60 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Active Proposals</span>
                    <span class="text-3xl font-black text-gray-900 block mt-1">{{ $topics->count() }}</span>
                </div>
                <div class="p-3 bg-red-50 rounded-xl text-red-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-200/60 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Approved Studies</span>
                    <span class="text-3xl font-black text-gray-900 block mt-1">{{ $topics->where('status', 'approved')->count() }}</span>
                </div>
                <div class="p-3 bg-green-50 rounded-xl text-green-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-200/60 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Under Evaluation</span>
                    <span class="text-3xl font-black text-gray-900 block mt-1">{{ $topics->whereIn('status', ['pending', 'resubmitted'])->count() }}</span>
                </div>
                <div class="p-3 bg-amber-50 rounded-xl text-amber-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-blue-200/60 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Action Required</span>
                    <span class="text-3xl font-black text-blue-700 block mt-1">{{ $topics->where('status', 'revision_requested')->count() }}</span>
                </div>
                <div class="p-3 bg-blue-50 rounded-xl text-blue-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 4.5h.008v.008H12V16.5z" /></svg>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200/60 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-base text-gray-900">My Submitted Manuscripts</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Real-time status loops for your active submissions.</p>
                </div>
            </div>
            @forelse ($topics as $topic)
                @php
                    $latestReview = $topic->reviews->last();
                    $isCurrentResubmission = (string) old('resubmitting_topic_id') === (string) $topic->id;
                @endphp
                <div class="border-b border-gray-100 p-5 last:border-b-0">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-sm font-bold text-gray-900">{{ $topic->title }}</h4>
                            <span class="rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider
                                {{ $topic->status === 'approved' ? 'bg-green-50 text-green-700' : '' }}
                                {{ $topic->status === 'rejected' ? 'bg-red-50 text-red-700' : '' }}
                                {{ $topic->status === 'pending' ? 'bg-amber-50 text-amber-700' : '' }}
                                {{ $topic->status === 'revision_requested' ? 'bg-blue-50 text-blue-700' : '' }}
                                {{ $topic->status === 'resubmitted' ? 'bg-purple-50 text-purple-700' : '' }}">
                                {{ str_replace('_', ' ', $topic->status) }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ $topic->description ?: 'No description provided.' }}</p>
                        <p class="mt-1 text-[11px] font-bold text-gray-500">
                            Estimated Budget: {{ $topic->estimated_budget !== null ? 'PHP '.number_format((float) $topic->estimated_budget, 2) : 'Not provided' }}
                        </p>
                        <p class="mt-1 text-[11px] font-semibold text-gray-400">{{ $topic->researchCall->title }} · {{ $topic->category->name }} · {{ $topic->estimated_duration_months }} months</p>
                        <p class="mt-1 text-[11px] font-medium text-gray-400">Submitted {{ $topic->created_at->diffForHumans() }}</p>

                        @if ($latestReview)
                            <div class="mt-3 rounded-xl border {{ $topic->status === 'revision_requested' ? 'border-blue-200 bg-blue-50' : 'border-gray-100 bg-gray-50' }} p-3">
                                <p class="text-[11px] font-black uppercase tracking-wider {{ $topic->status === 'revision_requested' ? 'text-blue-700' : 'text-gray-500' }}">
                                    Latest review: {{ str_replace('_', ' ', $latestReview->decision) }}
                                </p>
                                @if ($latestReview->comment)
                                    <p class="mt-1 whitespace-pre-line text-xs leading-relaxed text-gray-700">{{ $latestReview->comment }}</p>
                                @endif
                                <p class="mt-1 text-[11px] text-gray-400">
                                    {{ $latestReview->reviewer?->name ?? 'Research Head' }} · {{ $latestReview->created_at->format('M d, Y h:i A') }}
                                </p>
                            </div>
                        @endif

                        @if ($topic->reviews->count() > 1)
                            <details class="mt-2 text-xs text-gray-500">
                                <summary class="cursor-pointer font-bold">View all review history</summary>
                                <div class="mt-2 space-y-2 border-l-2 border-gray-100 pl-3">
                                    @foreach ($topic->reviews as $review)
                                        <div>
                                            <p class="text-[11px] font-bold uppercase tracking-wider">{{ str_replace('_', ' ', $review->decision) }}</p>
                                            @if ($review->comment)
                                                <p class="mt-0.5 whitespace-pre-line leading-relaxed">{{ $review->comment }}</p>
                                            @endif
                                            <p class="mt-0.5 text-[10px] text-gray-400">{{ $review->created_at->format('M d, Y h:i A') }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                        </div>

                        <a href="{{ route('topics.download', $topic) }}" class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700 transition hover:bg-gray-50">
                            Download latest
                        </a>
                    </div>

                    @if ($topic->status === 'revision_requested')
                        <details class="mt-4 rounded-2xl border border-blue-200 bg-blue-50/50 p-4" @if ($isCurrentResubmission && $errors->resubmission->any()) open @endif>
                            <summary class="cursor-pointer text-sm font-bold text-blue-800">Revise and resubmit proposal</summary>
                            <form action="{{ route('faculty.topics.resubmit', $topic) }}" method="POST" enctype="multipart/form-data" class="mt-4 grid gap-4 sm:grid-cols-2">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="resubmitting_topic_id" value="{{ $topic->id }}">

                                @if ($isCurrentResubmission && $errors->resubmission->any())
                                    <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-700 sm:col-span-2">
                                        <ul class="list-disc space-y-1 pl-4">
                                            @foreach ($errors->resubmission->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="space-y-1 sm:col-span-2">
                                    <label for="revision_title_{{ $topic->id }}" class="text-xs font-bold text-gray-600">Proposal title</label>
                                    <input id="revision_title_{{ $topic->id }}" name="title" type="text" value="{{ $isCurrentResubmission ? old('title') : $topic->title }}" required class="block w-full rounded-xl border-gray-200 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                </div>
                                <div class="space-y-1 sm:col-span-2">
                                    <label for="revision_description_{{ $topic->id }}" class="text-xs font-bold text-gray-600">Description</label>
                                    <textarea id="revision_description_{{ $topic->id }}" name="description" rows="3" class="block w-full rounded-xl border-gray-200 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">{{ $isCurrentResubmission ? old('description') : $topic->description }}</textarea>
                                </div>
                                <div class="space-y-1">
                                    <label for="revision_budget_{{ $topic->id }}" class="text-xs font-bold text-gray-600">Estimated budget (PHP)</label>
                                    <input id="revision_budget_{{ $topic->id }}" name="estimated_budget" type="number" value="{{ $isCurrentResubmission ? old('estimated_budget') : $topic->estimated_budget }}" min="0" max="9999999999.99" step="0.01" required class="block w-full rounded-xl border-gray-200 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                </div>
                                <div class="space-y-1">
                                    <label for="revision_duration_{{ $topic->id }}" class="text-xs font-bold text-gray-600">Estimated duration (months)</label>
                                    <input id="revision_duration_{{ $topic->id }}" name="estimated_duration_months" type="number" value="{{ $isCurrentResubmission ? old('estimated_duration_months') : $topic->estimated_duration_months }}" min="1" max="120" required class="block w-full rounded-xl border-gray-200 text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">
                                </div>
                                <div class="space-y-1">
                                    <label for="revision_document_{{ $topic->id }}" class="text-xs font-bold text-gray-600">Revised document</label>
                                    <input id="revision_document_{{ $topic->id }}" name="document" type="file" accept=".doc,.docx,.pdf" required class="block w-full rounded-xl border border-gray-200 bg-white p-2 text-xs text-gray-600">
                                </div>
                                <div class="sm:col-span-2 sm:text-right">
                                    <button type="submit" class="rounded-xl bg-blue-700 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-blue-800">Submit revision</button>
                                </div>
                            </form>
                        </details>
                    @endif
                </div>
            @empty
                <div class="p-12 text-center max-w-sm mx-auto flex flex-col items-center">
                    <div class="h-12 w-12 rounded-2xl bg-gray-50 flex items-center justify-center text-gray-400 mb-4 border border-gray-200/30">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m16.5 0a6 6 0 00-12 0m12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17.25" /></svg>
                    </div>
                    <h4 class="text-sm font-bold text-gray-800">No projects recorded</h4>
                    <p class="text-xs text-gray-400 mt-1 mb-4 leading-relaxed">You haven't uploaded any research proposals to the portal yet.</p>
                </div>
            @endforelse
        </div>

        <div id="submitProposalModal" class="{{ $errors->submission->any() ? '' : 'hidden' }} fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 sm:p-6">
            <div id="closeModalBackdrop" class="fixed inset-0 bg-gray-950/60 backdrop-blur-sm transition-opacity cursor-pointer"></div>

            <div class="relative bg-white rounded-3xl border border-gray-200/80 shadow-2xl w-full max-w-lg overflow-hidden transform transition-all z-10">
                <div class="px-6 pt-6 pb-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-black text-gray-900 tracking-tight">Submit Research Manuscript</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Download template guidelines and upload your copy.</p>
                    </div>
                    <button id="closeModalCrossBtn" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 transition cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form action="{{ route('faculty.topics') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-6">
                    @csrf
                    <div class="space-y-2">
                        <label for="research_call_id" class="text-xs font-black text-gray-400 uppercase tracking-wider block">Research Call</label>
                        <select id="research_call_id" name="research_call_id" required class="block w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600">
                            <option value="">Select an open call</option>
                            @foreach ($activeCalls as $call)<option value="{{ $call->id }}" @selected(old('research_call_id') == $call->id)>{{ $call->title }} (limit: {{ $call->max_proposals_per_faculty }})</option>@endforeach
                        </select>
                    </div>

                    <div class="space-y-2">
                        <label for="research_category_id" class="text-xs font-black text-gray-400 uppercase tracking-wider block">Research Category</label>
                        <select id="research_category_id" name="research_category_id" required class="block w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600">
                            <option value="">Select the most relevant category</option>
                            @foreach ($activeCalls as $call)
                                <optgroup label="{{ $call->title }}">@foreach ($call->categories as $category)<option value="{{ $category->id }}" data-call-id="{{ $call->id }}" @selected(old('research_category_id') == $category->id)>{{ $category->name }}</option>@endforeach</optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label for="title" class="text-xs font-black text-gray-400 uppercase tracking-wider block">Proposal Title</label>
                        <input id="title" name="title" type="text" value="{{ old('title') }}" required class="block w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600" placeholder="Enter your research title">
                    </div>

                    <div class="space-y-2">
                        <label for="description" class="text-xs font-black text-gray-400 uppercase tracking-wider block">Short Description</label>
                        <textarea id="description" name="description" rows="3" class="block w-full rounded-xl border-gray-200 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600" placeholder="Optional summary for the research head">{{ old('description') }}</textarea>
                    </div>

                    <div class="space-y-2">
                        <label for="estimated_budget" class="text-xs font-black text-gray-400 uppercase tracking-wider block">Estimated Proposal Budget</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-black text-gray-400">PHP</span>
                            <input id="estimated_budget" name="estimated_budget" type="number" value="{{ old('estimated_budget') }}" min="0" max="9999999999.99" step="0.01" required class="block w-full rounded-xl border-gray-200 pl-12 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600" placeholder="0.00">
                        </div>
                        @error('estimated_budget', 'submission')
                            <p class="text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="estimated_duration_months" class="text-xs font-black text-gray-400 uppercase tracking-wider block">Estimated Completion Time</label>
                        <div class="relative"><input id="estimated_duration_months" name="estimated_duration_months" type="number" value="{{ old('estimated_duration_months') }}" min="1" max="120" required class="block w-full rounded-xl border-gray-200 pr-20 text-sm text-gray-900 shadow-sm focus:border-red-600 focus:ring-red-600" placeholder="12"><span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-gray-400">months</span></div>
                    </div>

                    <div class="space-y-2">
                        <label class="text-xs font-black text-gray-400 uppercase tracking-wider block">Step 1: Download Target Template</label>
                        <div class="p-4 bg-gray-50 border border-gray-200/60 rounded-2xl flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-gray-800">ATHENA_Proposal_Form.docx</p>
                                    <p class="text-[11px] text-gray-400 font-medium">Standardized Layout</p>
                                </div>
                            </div>
                            <a href="#download-dummy-file" class="inline-flex items-center gap-1.5 bg-gray-900 hover:bg-gray-800 text-white text-xs font-bold px-3 py-2 rounded-xl transition duration-150 shadow-sm cursor-pointer">Download</a>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label for="document" class="text-xs font-black text-gray-400 uppercase tracking-wider block">Step 2: Upload Document File Copy</label>
                        <div id="documentDropzone" class="border-2 border-dashed border-gray-200 hover:border-red-500/50 rounded-2xl p-8 text-center bg-white transition relative group">
                            <input type="file" name="document" id="document" accept=".doc,.docx,.pdf" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                            <div class="flex flex-col items-center pointer-events-none">
                                <div class="h-10 w-10 rounded-xl bg-red-50 flex items-center justify-center text-red-600 mb-3 group-hover:scale-110 transition duration-200">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z"/></svg>
                                </div>
                                <p class="text-sm font-bold text-gray-800">Drag & drop completed copy here</p>
                                <p id="documentFileName" class="text-xs text-gray-400 mt-1">Accepts document extensions (.doc, .docx, .pdf) up to 25MB</p>
                            </div>
                        </div>
                        @error('document', 'submission')
                            <p class="text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                        <button id="closeModalCancelBtn" type="button" class="px-4 py-2.5 text-xs font-bold text-gray-500 hover:bg-gray-100 rounded-xl transition cursor-pointer">Cancel</button>
                        <button id="submitManuscriptFormBtn" type="submit" class="px-5 py-2.5 text-xs font-bold bg-red-600 hover:bg-red-700 text-white rounded-xl shadow-sm transition cursor-pointer">Submit Manuscript</button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script>
        function initializeWorkspaceEngine() {
            const modal = document.getElementById('submitProposalModal');
            const openBtn = document.getElementById('openSubmitModalBtn');
            const backdrop = document.getElementById('closeModalBackdrop');
            const crossBtn = document.getElementById('closeModalCrossBtn');
            const cancelBtn = document.getElementById('closeModalCancelBtn');
            const documentInput = document.getElementById('document');
            const documentDropzone = document.getElementById('documentDropzone');
            const documentFileName = document.getElementById('documentFileName');

            // Toggle function locked inside scope
            function setModalVisibility(visible) {
                if (!modal) return;
                if (visible) {
                    modal.classList.remove('hidden');
                } else {
                    modal.classList.add('hidden');
                }
            }

            // Bind click nodes cleanly
            if (openBtn) openBtn.onclick = () => setModalVisibility(true);
            if (backdrop) backdrop.onclick = () => setModalVisibility(false);
            if (crossBtn) crossBtn.onclick = () => setModalVisibility(false);
            if (cancelBtn) cancelBtn.onclick = () => setModalVisibility(false);

            function updateDocumentFileName() {
                if (!documentInput || !documentFileName) return;

                const file = documentInput.files && documentInput.files[0];
                documentFileName.textContent = file
                    ? `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`
                    : 'Accepts document extensions (.doc, .docx, .pdf) up to 25MB';

                documentFileName.classList.toggle('text-gray-800', Boolean(file));
                documentFileName.classList.toggle('font-bold', Boolean(file));
                documentFileName.classList.toggle('text-gray-400', !file);
            }

            if (documentInput) {
                documentInput.onchange = updateDocumentFileName;
                updateDocumentFileName();
            }

            if (documentDropzone && documentInput) {
                documentDropzone.ondragenter = (event) => {
                    event.preventDefault();
                    documentDropzone.classList.add('border-red-500/50', 'bg-red-50');
                };

                documentDropzone.ondragover = (event) => {
                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'copy';
                    documentDropzone.classList.add('border-red-500/50', 'bg-red-50');
                };

                documentDropzone.ondragleave = (event) => {
                    event.preventDefault();
                    if (!documentDropzone.contains(event.relatedTarget)) {
                        documentDropzone.classList.remove('border-red-500/50', 'bg-red-50');
                    }
                };

                documentDropzone.ondrop = (event) => {
                    event.preventDefault();
                    documentDropzone.classList.remove('border-red-500/50', 'bg-red-50');

                    if (event.dataTransfer.files.length) {
                        documentInput.files = event.dataTransfer.files;
                        updateDocumentFileName();
                    }
                };
            }

            // --- 🎞️ Carousel Logic Implementation ---
            let currentSlide = 0;
            const slides = document.querySelectorAll('.slide-item');
            const dots = document.querySelectorAll('.slide-dot');

            function goToSlide(index) {
                if (!slides.length || !dots.length) return;
                currentSlide = index;
                slides.forEach((slide, i) => {
                    if (i === currentSlide) {
                        slide.classList.remove('opacity-0', 'scale-105', 'pointer-events-none');
                        slide.classList.add('opacity-100', 'scale-100');
                        dots[i].classList.remove('w-1.5', 'bg-white/40');
                        dots[i].classList.add('w-5', 'bg-red-600');
                    } else {
                        slide.classList.remove('opacity-100', 'scale-100');
                        slide.classList.add('opacity-0', 'scale-105', 'pointer-events-none');
                        dots[i].classList.remove('w-5', 'bg-red-600');
                        dots[i].classList.add('w-1.5', 'bg-white/40');
                    }
                });
            }

            // Apply manual click indicators
            dots.forEach((dot, index) => {
                dot.onclick = () => goToSlide(index);
            });

            // Clean background loop clear setup
            if(window.workspaceCarouselInterval) clearInterval(window.workspaceCarouselInterval);
            
            window.workspaceCarouselInterval = setInterval(() => {
                if (slides.length) {
                    let nextSlide = (currentSlide + 1) % slides.length;
                    goToSlide(nextSlide);
                }
            }, 6000);
        }

        // 🚀 CRITICAL: Run initialization instantly AND on every Livewire page switch
        initializeWorkspaceEngine();
        document.addEventListener('livewire:navigated', initializeWorkspaceEngine);
    </script>
</x-app-layout>
