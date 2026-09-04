<x-app-layout>
    @php
        $pendingDecisionQuery = $isResearchHead && request()->query('decision') === 'revision_requested'
            ? '?decision=revision_requested'
            : '';
        $proposalWorkspaceUrl = $isResearchHead
            ? route('topics.show', $topic).$pendingDecisionQuery.'#file-review-card-'.$file->id
            : route('topics.show', $topic).'#submit-revision';
    @endphp

    @if (request()->boolean('revision_embed'))
        <x-proposal-revision-pdf :configuration="$annotationConfiguration" />
    @else
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                @unless ($isResearchHead)
                    <x-back-link href="{{ route('topics.show', $topic) }}#submit-revision">Back to proposal workspace</x-back-link>
                @endunless
                <h2 class="text-2xl font-black tracking-tight text-gray-900 {{ $isResearchHead ? '' : 'mt-2' }}">PDF Revision Annotations</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $file->label() }} · {{ $file->original_filename }} · Version {{ $version->version_number }}</p>
            </div>
            @if ($isResearchHead)
                <x-back-link href="{{ $proposalWorkspaceUrl }}" class="fixed bottom-4 right-4 z-40 w-auto shrink-0 shadow-xl ring-1 ring-black/10 sm:bottom-6 sm:right-6">Return to review</x-back-link>
            @elseif (! $canAnnotate && $topic->user_id === Auth::id() && $topic->status === 'revision_requested')
                <a href="{{ $proposalWorkspaceUrl }}" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">Revise {{ $file->label() }}</a>
            @endif
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
                <p class="mt-1">Use <span class="font-black">Precise area</span> for the most control: drag a tight box around only the passage, table, or image that needs revision. Use <span class="font-black">Select text</span> when you need to quote exact words.</p>
                <p class="mt-2 rounded-xl bg-white px-3 py-2 text-sm font-semibold text-red-900">Your highlights and comments are saved as drafts until you submit the Research Head decision. The paper will be marked for revision in the file checklist.</p>
            </div>
        @elseif ($isResearchHead)
            <div class="rounded-2xl border border-gray-300 bg-gray-100 p-4 text-sm leading-6 text-gray-800">
                Annotation editing is closed for this proposal status. Existing highlights remain available for review. Return to the proposal workspace to continue the Research Head workflow.
            </div>
        @else
            <div class="rounded-2xl border border-gray-300 bg-gray-100 p-4 text-sm leading-6 text-gray-800">
                Use the edit action on a comment to open its linked field with the revision instructions beside it. Paper-level comments open the matching editor. Your submitted PDF stays unchanged until you submit the revision.
            </div>
        @endif

        <div x-show="paperFocusOpen" x-cloak x-transition.opacity @click="closePaperFocus()" class="fixed inset-0 z-[90] bg-gray-950/75 backdrop-blur-sm" aria-hidden="true"></div>

        <div
            x-ref="paperFocusPanel"
            :class="paperFocusOpen ? 'fixed inset-3 z-[100] flex flex-col rounded-2xl border-white/10 shadow-2xl sm:inset-8' : 'rounded-2xl shadow-sm'"
            :role="paperFocusOpen ? 'dialog' : null"
            :aria-modal="paperFocusOpen ? 'true' : null"
            :aria-label="paperFocusOpen ? 'Focused PDF review workspace' : null"
            @keydown.escape.window="closePaperFocus()"
            class="overflow-hidden border border-gray-200 bg-white"
        >
            <div class="flex flex-col gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    @if ($canAnnotate)
                        <button type="button" @click="setMode('area')" :class="mode === 'area' ? 'bg-red-700 text-white' : 'border border-gray-300 bg-white text-gray-700'" class="rounded-xl px-4 py-2 text-sm font-black focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">Precise area <span class="ml-1 text-[10px] uppercase tracking-wider opacity-80">Recommended</span></button>
                        <button type="button" @click="setMode('text')" :class="mode === 'text' ? 'bg-red-700 text-white' : 'border border-gray-300 bg-white text-gray-700'" class="rounded-xl px-4 py-2 text-sm font-black focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">Select text</button>
                    @endif
                    <p class="text-sm text-gray-500" x-text="modeInstruction"></p>
                </div>
                <div class="flex items-center gap-2">
                    <button x-show="!paperFocusOpen" type="button" @click="openPaperFocus()" class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm font-black text-gray-700 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3m8 0h3a2 2 0 0 0 2-2v-3" /></svg>
                        Expand paper
                    </button>
                    <button x-ref="paperFocusClose" x-show="paperFocusOpen" x-cloak type="button" @click="closePaperFocus()" class="inline-flex items-center justify-center gap-2 rounded-xl bg-gray-950 px-3 py-2 text-sm font-black text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 6 12 12M18 6 6 18" /></svg>
                        Exit focus
                    </button>
                </div>
            </div>

            <div :class="paperFocusOpen ? 'min-h-0 flex-1 overflow-y-auto lg:overflow-hidden' : 'min-h-[70vh]'" class="grid lg:grid-cols-[minmax(0,1fr)_360px]">
                <main :class="paperFocusOpen ? 'min-h-[70vh] lg:min-h-0' : ''" class="min-w-0 overflow-hidden bg-slate-200/70">
                    <div x-show="loading" class="flex min-h-[60vh] items-center justify-center p-8 text-center"><div><p class="text-sm font-black text-gray-800">Loading submitted PDF…</p><p class="mt-1 text-xs text-gray-500">Preparing selectable text and annotation layers.</p></div></div>
                    <div x-show="loadError" x-cloak class="m-5 rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-800" x-text="loadError"></div>
                    <div x-ref="viewer" @mouseup="captureTextSelection" :class="{ 'pdf-annotation-area-mode': mode === 'area', 'h-full': paperFocusOpen }" class="pdf-annotation-viewer flex flex-col items-center gap-5 overflow-auto p-4 sm:p-6"></div>

                    <div x-ref="selectionToolbar" x-show="selectionToolbarVisible" x-cloak class="fixed z-50 flex -translate-x-1/2 gap-2 rounded-xl border border-red-300 bg-white p-2 shadow-xl">
                        <button type="button" @click="beginTextComment" class="rounded-lg bg-red-700 px-3 py-2 text-sm font-black text-white hover:bg-red-800">Use exact selection</button>
                        <button type="button" @click="cancelPendingSelection" class="rounded-lg px-3 py-2 text-sm font-bold text-gray-600 hover:bg-gray-100">Cancel</button>
                    </div>
                </main>

                <aside :class="paperFocusOpen ? 'min-h-0 overflow-y-auto' : ''" class="border-t border-gray-200 bg-white lg:border-l lg:border-t-0">
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
                                <label x-show="editorTargets.length > 0" class="mt-3 block text-sm font-black text-red-900">
                                    Where should the faculty make this change? <span class="text-red-700">Required</span>
                                    <select x-model="draftEditorTarget" class="mt-1 block w-full rounded-xl border-red-300 bg-white text-sm text-gray-900 focus:border-red-600 focus:ring-red-600">
                                        <option value="">Choose the matching editor field</option>
                                        <template x-for="target in editorTargets" :key="target.value">
                                            <option :value="target.value" x-text="target.label"></option>
                                        </template>
                                        <option value="__paper__">No matching field — open the paper only</option>
                                    </select>
                                    <span class="mt-1 block text-xs font-normal leading-5 text-red-800">This creates a direct “Edit this field” action for faculty.</span>
                                </label>
                                <p x-show="saveError" class="mt-2 text-xs font-semibold text-red-700" x-text="saveError"></p>
                                <button type="button" @click="saveAnnotation" :disabled="saving || !draftComment.trim() || (editorTargets.length > 0 && !draftEditorTarget)" class="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-red-700 px-4 py-2.5 text-sm font-black text-white hover:bg-red-800 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-600"><span x-text="saving ? 'Saving…' : 'Save highlight'"></span></button>
                            </section>
                        @endif

                        <section aria-labelledby="annotation-comments-heading">
                            <div class="flex items-center gap-2">
                                <h3 id="annotation-comments-heading" class="text-sm font-black text-gray-900">Comments</h3>
                                <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-black text-gray-600" x-text="annotations.length"></span>
                            </div>
                            <div class="mt-3 max-h-[44vh] space-y-3 overflow-auto pr-1">
                                <template x-for="annotation in annotations" :key="annotation.id">
                                    <article @click="jumpToAnnotation(annotation)" :class="selectedAnnotationId === annotation.id ? 'border-red-500 ring-2 ring-red-200' : 'border-gray-200'" class="cursor-pointer rounded-xl border bg-white p-3 transition hover:border-red-300">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="text-xs font-black text-gray-500">Page <span x-text="annotation.pageNumber"></span></p>
                                            <span :class="annotation.state === 'resolved' ? 'bg-gray-950 text-white' : (annotation.state === 'requested' ? 'bg-red-700 text-white' : 'bg-red-50 text-red-800')" class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-wider" x-text="annotationStateLabel(annotation)"></span>
                                        </div>
                                        <blockquote x-show="annotation.selectedText" class="mt-2 line-clamp-3 rounded-lg bg-red-50 px-2 py-1.5 text-sm italic leading-6 text-gray-700" x-text="annotation.selectedText"></blockquote>
                                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-800" x-text="annotation.comment"></p>
                                        <p class="mt-2 inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-amber-900" x-text="annotation.editorTargetLabel || 'Paper-level feedback'"></p>
                                        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-xs text-gray-500"><span x-text="annotation.reviewer"></span> · <span x-text="annotation.createdAt"></span></p>
                                            <button data-remove-annotation x-show="canAnnotate && annotation.state === 'draft'" type="button" @click.stop="deleteAnnotation(annotation)" class="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-bold text-red-700 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14M10 10v6m4-6v6" /></svg>
                                                Remove
                                            </button>
                                        </div>
                                        <p x-show="annotation.state !== 'draft'" class="mt-3 border-t border-gray-100 pt-3 text-xs leading-5 text-gray-500">
                                            Locked after the decision was sent.
                                        </p>
                                        <a
                                            x-show="!isResearchHead && annotation.state === 'requested' && revisionUrl"
                                            :href="annotationEditUrl(annotation)"
                                            @click.stop
                                            class="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-red-700 px-3 py-2 text-xs font-black text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-700 focus:ring-offset-2"
                                        >
                                            <span x-text="annotation.editorTargetLabel ? `Edit ${annotation.editorTargetLabel}` : 'Revise this paper'"></span>
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
                                        </a>
                                    </article>
                                </template>
                                <p x-show="annotations.length === 0" class="rounded-xl bg-gray-50 p-4 text-center text-xs text-gray-500">No highlighted revision comments yet.</p>
                            </div>
                        </section>

                        @if ($canAnnotate)
                            <section x-show="revisionCandidates.length > 0" x-cloak class="rounded-2xl border border-red-200 bg-red-50 p-4">
                                <h3 class="text-sm font-black text-red-950">Ready to send</h3>
                                <p class="mt-1 text-xs text-red-800"><span x-text="revisionCandidates.length"></span> <span x-text="revisionCandidates.length === 1 ? 'paper' : 'papers'"></span> marked for revision</p>
                                <div class="mt-3 divide-y divide-red-100 overflow-hidden rounded-xl bg-white">
                                    <template x-for="candidate in revisionCandidates" :key="candidate.fileId">
                                        <div class="flex items-center justify-between gap-3 px-3 py-2.5 text-xs text-gray-700">
                                            <span class="font-bold" x-text="candidate.label"></span>
                                            <span class="inline-flex min-w-6 shrink-0 items-center justify-center rounded-full bg-red-50 px-2 py-0.5 font-black text-red-700"><span x-text="candidate.annotationCount"></span><span class="sr-only" x-text="candidate.annotationCount === 1 ? ' comment' : ' comments'"></span></span>
                                        </div>
                                    </template>
                                </div>
                                <a href="{{ $proposalWorkspaceUrl }}" class="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-red-700 px-4 py-3 text-sm font-black text-white hover:bg-red-800">Return to file checklist</a>
                            </section>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    </div>
    @endif
</x-app-layout>
