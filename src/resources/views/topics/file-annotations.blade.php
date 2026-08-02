<x-app-layout>
    @php
        $proposalWorkspaceUrl = $isResearchHead
            ? route('topics.show', $topic).'#proposal-review'
            : route('faculty.proposal-drafts.revision', [
                'topic' => $topic,
                'document_type' => $file->document_type,
            ]);
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <x-back-link href="{{ $proposalWorkspaceUrl }}">Back to proposal workspace</x-back-link>
                <h2 class="mt-2 text-2xl font-black tracking-tight text-gray-900">PDF Revision Annotations</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $file->label() }} · {{ $file->original_filename }} · Version {{ $version->version_number }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($isResearchHead)
                    <a href="{{ $proposalWorkspaceUrl }}" class="inline-flex items-center justify-center rounded-xl border border-red-200 bg-white px-4 py-2.5 text-sm font-bold text-red-700 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Return to review</a>
                @elseif (! $canAnnotate && $topic->user_id === Auth::id() && $topic->status === 'revision_requested')
                    <a href="{{ $proposalWorkspaceUrl }}" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Revise {{ $file->label() }}</a>
                @endif
                <span class="inline-flex w-fit rounded-full px-3 py-1.5 text-sm font-black {{ $canAnnotate ? 'bg-red-700 text-white' : 'bg-gray-100 text-gray-700' }}">{{ $canAnnotate ? 'Annotation mode' : 'Read-only annotations' }}</span>
            </div>
        </div>
    </x-slot>

    <div
        x-data="pdfAnnotationWorkspace"
        data-pdf-annotation-config='@json($annotationConfiguration)'
        class="mx-auto max-w-[1600px] space-y-5 px-4 py-6 sm:px-6 lg:px-8"
    >
        @if ($canAnnotate)
            <div data-annotation-tools-guide class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-950">
                <p class="font-black">Highlight the exact part that needs revision.</p>
                <p class="mt-1">Choose <span class="font-black">Select text</span> and drag across words, or choose <span class="font-black">Draw area</span> for a table, image, or scanned section. Add the revision comment in the right panel, then save the highlight.</p>
                <p class="mt-2 rounded-xl bg-white px-3 py-2 text-sm font-semibold text-red-900">Your highlights and comments are saved as drafts until you submit the Research Head decision. The paper will be marked for revision in the file checklist.</p>
            </div>
        @elseif ($isResearchHead)
            <div class="rounded-2xl border border-gray-300 bg-gray-100 p-4 text-sm leading-6 text-gray-800">
                Annotation editing is closed for this proposal status. Existing highlights remain available for review. Return to the proposal workspace to continue the Research Head workflow.
            </div>
        @else
            <div class="rounded-2xl border border-gray-300 bg-gray-100 p-4 text-sm leading-6 text-gray-800">
                These comments are read-only. The submitted faculty PDF remains unchanged; the highlights are stored as ATHENA revision records. After reviewing them, open the matching paper editor to make the required changes.
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($canAnnotate)
                        <button type="button" @click="setMode('text')" :class="mode === 'text' ? 'bg-red-700 text-white' : 'border border-gray-300 bg-white text-gray-700'" class="rounded-xl px-4 py-2 text-sm font-black focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">Select text</button>
                        <button type="button" @click="setMode('area')" :class="mode === 'area' ? 'bg-red-700 text-white' : 'border border-gray-300 bg-white text-gray-700'" class="rounded-xl px-4 py-2 text-sm font-black focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">Draw area</button>
                    @endif
                    <p class="text-sm text-gray-500" x-text="modeInstruction"></p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="changeZoom(-0.15)" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-300 bg-white text-sm font-black text-gray-700 hover:bg-gray-100" aria-label="Zoom out">−</button>
                    <span class="min-w-[64px] text-center text-sm font-black text-gray-700" x-text="`${Math.round(scale * 100)}%`"></span>
                    <button type="button" @click="changeZoom(0.15)" class="flex h-9 w-9 items-center justify-center rounded-xl border border-gray-300 bg-white text-sm font-black text-gray-700 hover:bg-gray-100" aria-label="Zoom in">+</button>
                </div>
            </div>

            <div class="grid min-h-[70vh] lg:grid-cols-[minmax(0,1fr)_360px]">
                <main class="min-w-0 bg-slate-200/70">
                    <div x-show="loading" class="flex min-h-[60vh] items-center justify-center p-8 text-center"><div><p class="text-sm font-black text-gray-800">Loading submitted PDF…</p><p class="mt-1 text-xs text-gray-500">Preparing selectable text and annotation layers.</p></div></div>
                    <div x-show="loadError" x-cloak class="m-5 rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-800" x-text="loadError"></div>
                    <div x-ref="viewer" @mouseup="captureTextSelection" :class="mode === 'area' ? 'pdf-annotation-area-mode' : ''" class="pdf-annotation-viewer flex flex-col items-center gap-5 overflow-auto p-4 sm:p-6"></div>

                    <div x-ref="selectionToolbar" x-show="selectionToolbarVisible" x-cloak class="fixed z-50 flex -translate-x-1/2 gap-2 rounded-xl border border-red-300 bg-white p-2 shadow-xl">
                        <button type="button" @click="beginTextComment" class="rounded-lg bg-red-700 px-3 py-2 text-sm font-black text-white hover:bg-red-800">Highlight &amp; comment</button>
                        <button type="button" @click="cancelPendingSelection" class="rounded-lg px-3 py-2 text-sm font-bold text-gray-600 hover:bg-gray-100">Cancel</button>
                    </div>
                </main>

                <aside class="border-t border-gray-200 bg-white lg:border-l lg:border-t-0">
                    <div class="space-y-5 p-4 sm:p-5 lg:sticky lg:top-4">
                        @if ($canAnnotate)
                            <section x-show="draftSelection" x-cloak class="rounded-2xl border border-red-300 bg-red-50 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div><p class="text-xs font-black uppercase tracking-wider text-red-800">New revision comment</p><p class="mt-1 text-sm font-bold text-red-950">Page <span x-text="draftSelection?.pageNumber"></span> · <span x-text="draftSelection?.type === 'text' ? 'Text highlight' : 'Area highlight'"></span></p></div>
                                    <button type="button" @click="cancelDraft" class="text-sm font-black text-red-800">Cancel</button>
                                </div>
                                <blockquote x-show="draftSelection?.selectedText" class="mt-3 max-h-24 overflow-auto rounded-xl bg-white/80 p-3 text-sm leading-6 text-gray-700" x-text="draftSelection?.selectedText"></blockquote>
                                <label class="mt-3 block text-sm font-black text-red-900">What should the faculty revise?
                                    <textarea x-ref="commentInput" x-model="draftComment" rows="4" maxlength="5000" class="mt-1 block w-full rounded-xl border-red-300 text-sm focus:border-red-600 focus:ring-red-600" placeholder="Explain the required change clearly."></textarea>
                                </label>
                                <p x-show="saveError" class="mt-2 text-xs font-semibold text-red-700" x-text="saveError"></p>
                                <button type="button" @click="saveAnnotation" :disabled="saving || !draftComment.trim()" class="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-black text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-600"><span x-text="saving ? 'Saving…' : 'Save highlight'"></span></button>
                            </section>
                        @endif

                        <section aria-labelledby="annotation-comments-heading">
                            <div class="flex items-center justify-between gap-3"><div><h3 id="annotation-comments-heading" class="text-sm font-black text-gray-900">Revision comments</h3><p class="mt-1 text-xs text-gray-500"><span x-text="annotations.length"></span> highlight(s) on this file</p></div></div>
                            <div class="mt-3 max-h-[44vh] space-y-3 overflow-auto pr-1">
                                <template x-for="annotation in annotations" :key="annotation.id">
                                    <article @click="jumpToAnnotation(annotation)" :class="selectedAnnotationId === annotation.id ? 'border-red-500 ring-2 ring-red-200' : 'border-gray-200'" class="cursor-pointer rounded-xl border bg-white p-3 transition hover:border-red-300">
                                        <div class="flex items-start justify-between gap-3">
                                            <div><p class="text-xs font-black uppercase tracking-wider text-gray-500">Page <span x-text="annotation.pageNumber"></span> · <span x-text="annotation.type === 'text' ? 'Text' : 'Area'"></span></p><span :class="annotation.state === 'resolved' ? 'bg-gray-950 text-white' : (annotation.state === 'requested' ? 'bg-red-700 text-white' : 'bg-red-50 text-red-800')" class="mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-black uppercase" x-text="annotationStateLabel(annotation)"></span></div>
                                            <button x-show="canAnnotate && annotation.state === 'draft'" type="button" @click.stop="deleteAnnotation(annotation)" class="text-xs font-black text-red-600 hover:text-red-700">Delete</button>
                                        </div>
                                        <blockquote x-show="annotation.selectedText" class="mt-2 line-clamp-3 rounded-lg bg-red-50 px-2 py-1.5 text-sm italic leading-6 text-gray-700" x-text="annotation.selectedText"></blockquote>
                                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-800" x-text="annotation.comment"></p>
                                        <p class="mt-2 text-xs text-gray-500"><span x-text="annotation.reviewer"></span> · <span x-text="annotation.createdAt"></span></p>
                                    </article>
                                </template>
                                <p x-show="annotations.length === 0" class="rounded-xl bg-gray-50 p-4 text-center text-xs text-gray-500">No highlighted revision comments yet.</p>
                            </div>
                        </section>

                        @if ($canAnnotate)
                            <section x-show="revisionCandidates.length > 0" x-cloak class="rounded-2xl border border-red-200 bg-red-50 p-4">
                                <h3 class="text-base font-black text-red-950">Highlights saved</h3>
                                <p class="mt-1 text-sm leading-6 text-red-900"><span x-text="revisionCandidates.length"></span> paper(s) are now marked for revision. Return to the checklist, upload the evaluation proof, and send the decision.</p>
                                <div class="mt-3 space-y-2">
                                    <template x-for="candidate in revisionCandidates" :key="candidate.fileId">
                                        <div class="rounded-xl bg-white px-3 py-2 text-xs text-gray-700"><span class="font-black" x-text="candidate.label"></span><span class="ml-1 text-gray-500">· <span x-text="candidate.annotationCount"></span> comment(s)</span></div>
                                    </template>
                                </div>
                                <a href="{{ route('topics.show', $topic) }}#proposal-review" class="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-red-700 px-4 py-3 text-sm font-black text-white hover:bg-red-800">Return to file checklist</a>
                            </section>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    </div>
</x-app-layout>
