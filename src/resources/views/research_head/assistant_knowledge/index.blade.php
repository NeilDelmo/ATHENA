<x-app-layout>
    <x-slot name="header">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-purple-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-purple-700 dark:bg-purple-950/50 dark:text-purple-200">Grounded AI</span>
                <span class="rounded-full bg-green-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-green-700 dark:bg-green-950/50 dark:text-green-200">Research Head controlled</span>
            </div>
            <h2 class="mt-3 text-2xl font-black tracking-tight text-gray-900">Athena Knowledge Base</h2>
            <p class="mt-1 text-xs text-gray-500">Feed approved institutional guidance to Athena as reviewable text. Relevant excerpts are retrieved automatically when faculty ask questions.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200">
                <p class="font-black">The knowledge entry could not be saved.</p>
                <p class="mt-1 text-xs">{{ $errors->first() }}</p>
            </div>
        @endif

        <section class="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-xs leading-5 text-blue-800 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-200">
            <p class="font-black">How grounding works</p>
            <p class="mt-1">Athena searches active entries for terms related to the faculty question, sends only the best matching excerpts to Gemini, and asks the model to cite them as <span class="font-black">[ATHENA 1]</span>, <span class="font-black">[ATHENA 2]</span>, and so on. Do not add confidential participant data, passwords, or unpublished sensitive results.</p>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div>
                <h3 class="text-base font-black text-gray-900">Add approved guidance</h3>
                <p class="mt-1 text-xs text-gray-500">Paste the authoritative text Athena may use. Keep each entry focused on one policy, workflow, or guidance topic.</p>
            </div>

            <form method="POST" action="{{ route('research_head.assistant-knowledge.store') }}" class="mt-5 grid gap-4 md:grid-cols-2">
                @csrf
                <div>
                    <label for="knowledge_title" class="text-xs font-bold text-gray-600">Title</label>
                    <input id="knowledge_title" name="title" value="{{ old('title') }}" required maxlength="255" placeholder="Example: Research ethics clearance process" class="mt-1 block w-full rounded-xl border-gray-200 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                </div>
                <div>
                    <label for="knowledge_category" class="text-xs font-bold text-gray-600">Category</label>
                    <select id="knowledge_category" name="category" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        @foreach ($categoryOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('category', 'general') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="knowledge_content" class="text-xs font-bold text-gray-600">Approved knowledge text</label>
                    <textarea id="knowledge_content" name="content" required minlength="20" maxlength="20000" rows="7" placeholder="Paste the approved policy excerpt, process, requirements, deadlines, or guidance Athena should use..." class="mt-1 block w-full rounded-xl border-gray-200 text-sm leading-6 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('content') }}</textarea>
                </div>
                <div>
                    <label for="knowledge_source_url" class="text-xs font-bold text-gray-600">Official source URL <span class="font-normal text-gray-400">(optional)</span></label>
                    <input id="knowledge_source_url" name="source_url" type="url" value="{{ old('source_url') }}" maxlength="2048" placeholder="https://..." class="mt-1 block w-full rounded-xl border-gray-200 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                </div>
                <div class="flex items-end justify-end">
                    <button class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-black text-white transition hover:bg-red-700">Add to Athena</button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-gray-100 p-5 dark:border-slate-800">
                <div>
                    <h3 class="text-base font-black text-gray-900">Managed knowledge</h3>
                    <p class="mt-1 text-xs text-gray-500">Archived entries remain here but are never sent to the chatbot.</p>
                </div>
                <p class="text-[10px] font-black uppercase tracking-wider text-gray-400">{{ $entries->where('is_active', true)->count() }} active · {{ $entries->count() }} total</p>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-slate-800">
                @forelse ($entries as $entry)
                    <article class="p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-sm font-black text-gray-900">{{ $entry->title }}</h4>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase {{ $entry->is_active ? 'bg-green-50 text-green-700 dark:bg-green-950/50 dark:text-green-200' : 'bg-gray-100 text-gray-500 dark:bg-slate-800 dark:text-slate-400' }}">{{ $entry->is_active ? 'Active' : 'Archived' }}</span>
                                    <span class="rounded-full bg-purple-50 px-2 py-0.5 text-[10px] font-black text-purple-700 dark:bg-purple-950/50 dark:text-purple-200">{{ $entry->categoryLabel() }}</span>
                                </div>
                                <p class="mt-1 text-[10px] text-gray-400">Updated {{ $entry->updated_at->diffForHumans() }} · Added by {{ $entry->creator?->name ?? 'Former Research Head' }}</p>
                            </div>
                            <form method="POST" action="{{ route('research_head.assistant-knowledge.status', $entry) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="is_active" value="{{ $entry->is_active ? 0 : 1 }}">
                                <button class="rounded-lg border px-3 py-2 text-[10px] font-black uppercase tracking-wider transition {{ $entry->is_active ? 'border-gray-200 text-gray-500 hover:border-red-200 hover:text-red-600 dark:border-slate-700' : 'border-green-200 text-green-700 hover:bg-green-50 dark:border-green-900 dark:text-green-300 dark:hover:bg-green-950/40' }}">{{ $entry->is_active ? 'Archive' : 'Restore' }}</button>
                            </form>
                        </div>

                        <form method="POST" action="{{ route('research_head.assistant-knowledge.update', $entry) }}" class="mt-4 grid gap-3 md:grid-cols-2">
                            @csrf
                            @method('PUT')
                            <div>
                                <label for="entry_title_{{ $entry->id }}" class="sr-only">Title</label>
                                <input id="entry_title_{{ $entry->id }}" name="title" value="{{ $entry->title }}" required maxlength="255" class="block w-full rounded-xl border-gray-200 text-xs font-bold dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </div>
                            <div>
                                <label for="entry_category_{{ $entry->id }}" class="sr-only">Category</label>
                                <select id="entry_category_{{ $entry->id }}" name="category" required class="block w-full rounded-xl border-gray-200 text-xs dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                    @foreach ($categoryOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($entry->category === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label for="entry_content_{{ $entry->id }}" class="sr-only">Knowledge text</label>
                                <textarea id="entry_content_{{ $entry->id }}" name="content" required minlength="20" maxlength="20000" rows="5" class="block w-full rounded-xl border-gray-200 text-xs leading-5 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ $entry->content }}</textarea>
                            </div>
                            <div>
                                <label for="entry_source_{{ $entry->id }}" class="sr-only">Official source URL</label>
                                <input id="entry_source_{{ $entry->id }}" name="source_url" type="url" value="{{ $entry->source_url }}" maxlength="2048" placeholder="Official source URL (optional)" class="block w-full rounded-xl border-gray-200 text-xs dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </div>
                            <div class="flex items-center justify-end">
                                <button class="rounded-lg bg-gray-900 px-4 py-2 text-[10px] font-black uppercase tracking-wider text-white transition hover:bg-gray-800 dark:bg-slate-700 dark:hover:bg-slate-600">Save changes</button>
                            </div>
                        </form>
                    </article>
                @empty
                    <div class="p-8 text-center">
                        <p class="text-sm font-black text-gray-800">No manual knowledge entries yet</p>
                        <p class="mt-1 text-xs text-gray-500">Athena still retrieves active research calls and proposal-template instructions automatically.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
