<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-black tracking-tight text-gray-900">Proposal Template Administration</h2>
            <p class="mt-1 text-xs text-gray-500">Maintain official forms and assign each one to its correct workflow stage.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><p class="font-bold">The template could not be saved.</p><p class="mt-1 text-xs">{{ $errors->first() }}</p></div>
        @endif

        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-xs leading-5 text-blue-800">
            This area manages official forms only. Athena's approved guidance is managed separately in the <a href="{{ route('research_head.assistant-knowledge.index') }}" class="font-black underline decoration-blue-300 underline-offset-2">Athena Knowledge Base</a>, while faculty use the chatbot in the Research Support workspace.
        </div>

        <div x-data="{ activeTab: @js($errors->any() && ! old('editing_template') ? 'upload' : 'managed') }" class="space-y-5">
            <div class="overflow-x-auto border-b border-gray-200" role="tablist" aria-label="Proposal template administration">
                <nav class="flex min-w-max gap-6">
                    <button id="templates-tab-upload" type="button" role="tab" :aria-selected="activeTab === 'upload'" aria-controls="templates-panel-upload" @click="activeTab = 'upload'" :class="activeTab === 'upload' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-xs font-bold transition">
                        Upload an additional template
                    </button>
                    <button id="templates-tab-managed" type="button" role="tab" :aria-selected="activeTab === 'managed'" aria-controls="templates-panel-managed" @click="activeTab = 'managed'" :class="activeTab === 'managed' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:border-red-300 hover:text-red-600'" class="flex items-center gap-2 border-b-2 px-1 pb-3 text-xs font-bold transition">
                        Templates
                    </button>
                </nav>
            </div>

            <div id="templates-panel-upload" x-show="activeTab === 'upload'" x-cloak role="tabpanel" aria-labelledby="templates-tab-upload">
                <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="mb-5"><h3 class="text-base font-black text-gray-900">Upload an additional template</h3><p class="mt-1 text-xs text-gray-500">Active templates appear only at the workflow stage selected below.</p></div>
            <form method="POST" action="{{ route('research_head.proposal-templates.store') }}" enctype="multipart/form-data" class="grid gap-4 md:grid-cols-2">
                @csrf
                <div><label for="template_name" class="text-xs font-bold text-gray-600">Template name</label><input id="template_name" name="name" value="{{ old('name') }}" required maxlength="255" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
                <div><label for="template_revision" class="text-xs font-bold text-gray-600">Revision or effectivity label</label><input id="template_revision" name="revision_label" value="{{ old('revision_label') }}" maxlength="100" placeholder="Example: Revision 05 · Effective July 2026" class="mt-1 block w-full rounded-xl border-gray-200 text-sm"></div>
                <div><label for="template_stage" class="text-xs font-bold text-gray-600">Workflow stage</label><select id="template_stage" name="workflow_stage" required class="mt-1 block w-full rounded-xl border-gray-200 text-sm">@foreach (App\Models\ProposalTemplate::workflowStages() as $value => $label)<option value="{{ $value }}" @selected(old('workflow_stage') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="md:col-span-2"><label for="template_description" class="text-xs font-bold text-gray-600">Short description</label><textarea id="template_description" name="description" rows="2" maxlength="1000" class="mt-1 block w-full rounded-xl border-gray-200 text-sm">{{ old('description') }}</textarea></div>
                <div class="md:col-span-2"><label for="template_instructions" class="text-xs font-bold text-gray-600">Completion instructions</label><textarea id="template_instructions" name="instructions" rows="3" maxlength="2000" placeholder="Explain when and how faculty should complete this form." class="mt-1 block w-full rounded-xl border-gray-200 text-sm">{{ old('instructions') }}</textarea></div>
                <div class="md:col-span-2" x-data="fileDropzone({ accept: '.pdf,.doc,.docx,.xls,.xlsx', maxBytes: 25600 * 1024 })" @paste="paste($event)">
                    <label for="template_document" class="text-xs font-bold text-gray-600">Template file</label>
                    <label for="template_document" data-proposal-template-dropzone tabindex="0" @dragenter.prevent="dragEnter()" @dragover.prevent="dragEnter()" @dragleave.prevent="dragLeave()" @drop.prevent="drop($event)" @keydown.enter.prevent="browse()" @keydown.space.prevent="browse()" :class="dragging ? 'border-red-500 bg-red-50' : 'border-gray-200 bg-gray-50'" class="mt-1 flex min-h-32 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed p-5 text-center transition hover:border-red-400 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <input id="template_document" x-ref="input" name="document" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx" required @change="syncFiles(true)" class="sr-only">
                        <span x-show="files.length === 0" class="flex flex-col items-center gap-2">
                            <svg class="h-8 w-8 text-red-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V3.75m0 0L7.5 8.25M12 3.75l4.5 4.5M5.25 15.75v2.25A2.25 2.25 0 0 0 7.5 20.25h9a2.25 2.25 0 0 0 2.25-2.25v-2.25" /></svg>
                            <span class="text-sm font-black text-gray-700">Drop or paste a template file</span>
                            <span class="text-xs text-gray-400">or click to browse · PDF, Word, or Excel · up to 25 MB</span>
                        </span>
                        <span x-show="files.length > 0" x-cloak class="flex max-w-full flex-col items-center gap-1">
                            <span class="max-w-full truncate text-sm font-black text-red-700" x-text="files[0]?.name"></span>
                            <span class="text-xs font-semibold text-gray-500" x-text="formatSize(files[0]?.size || 0)"></span>
                        </span>
                    </label>
                    <p x-show="message" x-cloak role="alert" class="mt-2 text-xs font-semibold text-red-700" x-text="message"></p>
                </div>
                <div class="flex items-end justify-end md:col-span-2"><button class="rounded-xl bg-red-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-red-700">Upload template</button></div>
            </form>
                </section>
            </div>

            <div id="templates-panel-managed" x-show="activeTab === 'managed'" x-cloak role="tabpanel" aria-labelledby="templates-tab-managed">
                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 p-5"><h3 class="text-base font-black text-gray-900">Managed templates</h3><p class="mt-1 text-xs text-gray-500">Archived templates remain available here but disappear from faculty submission instructions.</p></div>
            <div class="divide-y divide-gray-100">
                @forelse ($templates as $template)
                    <article class="p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-sm font-black text-gray-900">{{ $template->name }}</h4>
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase {{ $template->is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $template->is_active ? 'Active' : 'Archived' }}</span>
                                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-[10px] font-black uppercase text-blue-700">{{ App\Models\ProposalTemplate::workflowStages()[$template->workflow_stage] ?? str($template->workflow_stage)->replace('_', ' ') }}</span>
                                    @if (! $template->file_available)<span class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-black uppercase text-red-700">File missing</span>@endif
                                </div>
                                @if ($template->revision_label)<p class="mt-1 text-[11px] font-bold uppercase tracking-wider text-red-600">{{ $template->revision_label }}</p>@endif
                                <p class="mt-2 text-xs leading-5 text-gray-500">{{ $template->description ?: 'No description provided.' }}</p>
                                @if ($template->instructions)<p class="mt-2 rounded-xl bg-gray-50 p-3 text-xs leading-5 text-gray-600"><span class="font-black">Instructions:</span> {{ $template->instructions }}</p>@endif
                                <div class="mt-3 flex flex-wrap gap-3 text-[11px] font-semibold text-gray-400"><span>{{ $template->original_filename }}</span>@if ($template->display_file_size)<span>{{ number_format($template->display_file_size / 1024, 1) }} KB</span>@endif<span>Updated {{ $template->updated_at->format('M d, Y') }}</span>@if ($template->uploader)<span>by {{ $template->uploader->name }}</span>@endif</div>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2">
                                @if ($template->file_available)<a href="{{ route('proposal-templates.download', $template) }}" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-bold text-gray-700">Download</a>@endif
                                <form method="POST" action="{{ route('research_head.proposal-templates.status', $template) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $template->is_active ? 0 : 1 }}"><button class="rounded-xl px-3 py-2 text-xs font-bold {{ $template->is_active ? 'bg-gray-100 text-gray-600' : 'bg-green-700 text-white' }}">{{ $template->is_active ? 'Archive' : 'Restore' }}</button></form>
                            </div>
                        </div>

                        <details class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4" @if (old('editing_template') === $template->slug) open @endif>
                            <summary class="cursor-pointer text-xs font-black text-gray-700">Edit details or replace file</summary>
                            <form method="POST" action="{{ route('research_head.proposal-templates.update', $template) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-2">
                                @csrf @method('PUT')
                                <input type="hidden" name="editing_template" value="{{ $template->slug }}">
                                <div><label class="text-[11px] font-bold text-gray-500">Name</label><input name="name" value="{{ old('editing_template') === $template->slug ? old('name') : $template->name }}" required maxlength="255" class="mt-1 block w-full rounded-xl border-gray-200 text-xs"></div>
                                <div><label class="text-[11px] font-bold text-gray-500">Revision label</label><input name="revision_label" value="{{ old('editing_template') === $template->slug ? old('revision_label') : $template->revision_label }}" maxlength="100" class="mt-1 block w-full rounded-xl border-gray-200 text-xs"></div>
                                <div><label class="text-[11px] font-bold text-gray-500">Workflow stage</label><select name="workflow_stage" required class="mt-1 block w-full rounded-xl border-gray-200 text-xs">@foreach (App\Models\ProposalTemplate::workflowStages() as $value => $label)<option value="{{ $value }}" @selected((old('editing_template') === $template->slug ? old('workflow_stage') : $template->workflow_stage) === $value)>{{ $label }}</option>@endforeach</select></div>
                                <div class="md:col-span-2"><label class="text-[11px] font-bold text-gray-500">Description</label><textarea name="description" rows="2" maxlength="1000" class="mt-1 block w-full rounded-xl border-gray-200 text-xs">{{ old('editing_template') === $template->slug ? old('description') : $template->description }}</textarea></div>
                                <div class="md:col-span-2"><label class="text-[11px] font-bold text-gray-500">Instructions</label><textarea name="instructions" rows="3" maxlength="2000" class="mt-1 block w-full rounded-xl border-gray-200 text-xs">{{ old('editing_template') === $template->slug ? old('instructions') : $template->instructions }}</textarea></div>
                                <div x-data="fileDropzone({ accept: '.pdf,.doc,.docx,.xls,.xlsx', maxBytes: 25600 * 1024 })" @paste="paste($event)">
                                    <label for="replacement_document_{{ $template->slug }}" class="text-[11px] font-bold text-gray-500">Replacement file (optional)</label>
                                    <label for="replacement_document_{{ $template->slug }}" data-proposal-template-replacement-dropzone tabindex="0" @dragenter.prevent="dragEnter()" @dragover.prevent="dragEnter()" @dragleave.prevent="dragLeave()" @drop.prevent="drop($event)" @keydown.enter.prevent="browse()" @keydown.space.prevent="browse()" :class="dragging ? 'border-red-500 bg-red-50' : 'border-gray-200 bg-white'" class="mt-1 flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed p-4 text-center transition hover:border-red-400 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500">
                                        <input id="replacement_document_{{ $template->slug }}" x-ref="input" name="document" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx" @change="syncFiles(true)" class="sr-only">
                                        <span x-show="files.length === 0" class="flex flex-col items-center gap-1">
                                            <span class="text-xs font-black text-gray-700">Drop or paste a replacement file</span>
                                            <span class="text-[11px] text-gray-400">or click to browse · PDF, Word, or Excel · up to 25 MB</span>
                                        </span>
                                        <span x-show="files.length > 0" x-cloak class="flex max-w-full flex-col items-center gap-1">
                                            <span class="max-w-full truncate text-xs font-black text-red-700" x-text="files[0]?.name"></span>
                                            <span class="text-[11px] font-semibold text-gray-500" x-text="formatSize(files[0]?.size || 0)"></span>
                                        </span>
                                    </label>
                                    <p x-show="message" x-cloak role="alert" class="mt-2 text-[11px] font-semibold text-red-700" x-text="message"></p>
                                </div>
                                <div class="flex items-end justify-end"><button class="rounded-xl bg-gray-900 px-4 py-2.5 text-xs font-bold text-white">Save changes</button></div>
                            </form>
                        </details>
                    </article>
                @empty
                    <div class="p-12 text-center text-sm font-bold text-gray-600">No proposal templates are configured.</div>
                @endforelse
            </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
