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
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Review document</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $file->label() }} · Version {{ $version->version_number }}</p>
                    <details class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        <summary class="cursor-pointer">File details</summary>
                        <p class="mt-1 max-w-xl break-words">{{ $file->original_filename }}</p>
                    </details>
                </div>
                <x-back-link href="{{ $proposalWorkspaceUrl }}">Back to review</x-back-link>
            </div>
        </x-slot>

        <div x-data="pdfAnnotationWorkspace" data-pdf-annotation-config='@json($annotationConfiguration)' @resize.window="positionCommentComposer()" @scroll.window.capture="positionCommentComposer()" class="mx-auto max-w-[1600px] space-y-4">
            @if (! $canAnnotate && $isResearchHead)
                <p class="rounded-xl bg-gray-100 px-4 py-3 text-sm text-gray-700 dark:bg-gray-900 dark:text-gray-300">This review is locked. Saved comments remain available, but cannot be changed after the decision is sent.</p>
            @elseif (! $isResearchHead)
                <p class="rounded-xl bg-gray-100 px-4 py-3 text-sm text-gray-700 dark:bg-gray-900 dark:text-gray-300">Select a numbered comment to find the part that needs revision. Open the paper or its linked field to make your changes.</p>
                @if ($topic->user_id === Auth::id() && $topic->status === 'revision_requested')
                    <a href="{{ $proposalWorkspaceUrl }}" class="inline-flex rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white">Revise {{ $file->label() }}</a>
                @endif
            @endif

            <div x-show="paperFocusOpen" x-cloak @click="closePaperFocus()" class="fixed inset-0 z-[90] bg-gray-950/70" aria-hidden="true"></div>
            <div
                x-ref="paperFocusPanel"
                :class="paperFocusOpen ? 'fixed inset-2 z-[100] flex flex-col shadow-2xl sm:inset-5' : ''"
                :role="paperFocusOpen ? 'dialog' : null"
                :aria-modal="paperFocusOpen ? 'true' : null"
                :aria-label="paperFocusOpen ? 'Focused PDF review workspace' : null"
                @keydown.escape.window="if (!draftSelection) closePaperFocus()"
                class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-3 py-3 dark:border-gray-800 sm:px-4">
                    <div data-annotation-tools-guide>
                        @if ($canAnnotate)
                            <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="reviewerReady ? 'Drag over the part that needs revision, then add a comment.' : 'Confirm the reviewer’s name to unlock highlighting.'"></p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="draftSelection ? modeInstruction : 'Comments remain drafts until you send the revision request.'"></p>
                        @else
                            <p class="text-sm text-gray-600 dark:text-gray-300" x-text="modeInstruction"></p>
                        @endif
                    </div>
                    <button x-ref="paperFocusClose" type="button" @click="paperFocusOpen ? closePaperFocus() : openPaperFocus()" class="shrink-0 rounded-lg border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-500 dark:border-gray-700 dark:text-gray-200" x-text="paperFocusOpen ? 'Exit focus' : 'Expand paper'">Expand paper</button>
                </div>

                <div :class="paperFocusOpen ? 'min-h-0 flex-1' : 'h-[76dvh] min-h-[32rem]'" class="grid grid-rows-[minmax(14rem,1fr)_auto] lg:grid-cols-[minmax(0,1fr)_320px] lg:grid-rows-1">
                    <main class="min-h-0 min-w-0 overflow-hidden rounded-bl-2xl bg-slate-100 dark:bg-slate-950">
                        <div x-show="loading" class="p-8 text-center text-sm text-gray-600">Loading submitted PDF…</div>
                        <div x-show="loadError" x-cloak role="alert" class="m-4 rounded-xl bg-red-50 p-4 text-sm text-red-800" x-text="loadError"></div>
                        <div x-ref="viewer" :data-active-reviewer="activeReviewer" tabindex="0" aria-label="Submitted document" @mouseup="captureTextSelection" :class="{ 'pdf-annotation-area-mode': mode !== 'text' }" class="pdf-annotation-viewer flex h-full flex-col items-center gap-5 overflow-auto overscroll-contain p-3 sm:p-5"></div>
                    </main>

                    <aside class="max-h-[34dvh] overflow-y-auto border-t border-gray-200 p-4 dark:border-gray-800 lg:max-h-none lg:border-l lg:border-t-0">
                        <div class="mb-4 space-y-2" aria-label="Reviewer profiles">
                            <p class="flex gap-3 text-[11px] font-semibold"><span class="text-red-700 dark:text-red-300">● Research Head · Red</span><span class="text-blue-700 dark:text-blue-300">● Co-evaluator · Blue</span></p>
                            <div class="grid grid-cols-2 gap-2" role="group" aria-label="Choose reviewer">
                                <button type="button" @click="switchReviewer('research_head')" :disabled="!!draftSelection || saving" :aria-pressed="activeReviewer === 'research_head'" :class="activeReviewer === 'research_head' ? 'border-red-700 bg-red-50 dark:bg-red-950/40' : 'border-gray-200 dark:border-gray-700'" class="flex min-w-0 items-center gap-2 rounded-xl border p-2 text-left disabled:opacity-60">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-red-100 text-xs font-bold text-red-900 dark:bg-red-900 dark:text-red-100">
                                        <template x-if="config.researchHeadAvatar"><img :src="config.researchHeadAvatar" alt="" class="h-full w-full object-cover" x-on:error="config.researchHeadAvatar = null"></template>
                                        <span x-show="!config.researchHeadAvatar" x-text="reviewerInitials(config.researchHeadName || 'Research Head')"></span>
                                    </span>
                                    <span class="min-w-0"><span class="block text-[11px] font-semibold text-gray-900 dark:text-white">Research Head</span><span class="block truncate text-[10px] text-gray-500 dark:text-gray-400" x-text="config.researchHeadName || 'Research Head'"></span></span>
                                </button>
                                <button type="button" @click="switchReviewer('co_evaluator')" :disabled="!!draftSelection || saving" :aria-pressed="activeReviewer === 'co_evaluator'" :class="activeReviewer === 'co_evaluator' ? 'border-blue-700 bg-blue-50 dark:bg-blue-950/40' : 'border-gray-200 dark:border-gray-700'" class="flex min-w-0 items-center gap-2 rounded-xl border p-2 text-left disabled:opacity-60">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-800 dark:bg-blue-900 dark:text-blue-100" x-text="reviewerInitials(coEvaluatorName)"></span>
                                    <span class="min-w-0"><span class="block text-[11px] font-semibold text-gray-900 dark:text-white">Co-evaluator</span><span class="block truncate text-[10px] text-gray-500 dark:text-gray-400" x-text="coEvaluatorName || 'External feedback'"></span></span>
                                </button>
                            </div>
                            @if ($canAnnotate)
                                <div x-show="activeReviewer === 'co_evaluator'" x-cloak class="rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/30">
                                    <div x-show="!reviewerReady">
                                        <label for="co-evaluator-name" class="block text-xs font-semibold text-gray-700 dark:text-gray-200">Who is this feedback from?</label>
                                        <input id="co-evaluator-name" x-ref="reviewerNameInput" x-model="coEvaluatorName" @keydown.enter.prevent="confirmReviewer" :disabled="!!draftSelection || saving" maxlength="160" placeholder="Co-evaluator’s full name" class="mt-2 block w-full rounded-lg border-blue-200 text-sm dark:border-blue-800 dark:bg-gray-950 dark:text-white">
                                        <button type="button" @click="confirmReviewer" :disabled="!coEvaluatorName.trim() || !!draftSelection || saving" class="mt-2 w-full rounded-lg bg-blue-700 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">Confirm reviewer</button>
                                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">Confirm the name, then highlight the paper and enter their comment.</p>
                                    </div>
                                    <div x-show="reviewerReady" x-cloak class="flex items-start gap-2" role="status" aria-live="polite">
                                        <svg :class="reviewerReady ? 'reviewer-lock-confirmed' : ''" class="h-5 w-5 shrink-0 text-blue-700 dark:text-blue-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg>
                                        <div class="min-w-0"><p class="break-words text-xs font-semibold text-blue-900 dark:text-blue-100" x-text="confirmedCoEvaluatorName"></p><p class="mt-1 text-xs text-blue-800 dark:text-blue-200">Ready to highlight · Blue comments</p><button type="button" @click="editReviewerName" :disabled="!!draftSelection || saving" class="mt-2 text-xs font-semibold text-blue-700 underline disabled:opacity-50 dark:text-blue-300">Change name</button></div>
                                    </div>
                                </div>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400" x-text="activeReviewer === 'co_evaluator' ? 'Enter their feedback on their behalf. Your account remains the recorder.' : 'Add your own review comments.'"></p>
                            @endif
                        </div>
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <h3 id="annotation-comments-heading" class="text-sm font-semibold text-gray-900 dark:text-white">Comments <span class="ml-1 text-gray-500" x-text="reviewerCommentCount"></span></h3>
                            <span class="text-xs text-gray-500" x-show="canAnnotate">Draft review</span>
                        </div>
                        <div class="space-y-3" aria-labelledby="annotation-comments-heading">
                            <template x-for="(annotation, index) in annotations" :key="annotation.id">
                                <article x-show="(annotation.feedbackSource || 'research_head') === activeReviewer" :data-comment-id="annotation.id" @click="jumpToAnnotation(annotation)" @keydown.enter.self.prevent="jumpToAnnotation(annotation)" tabindex="0" :aria-label="'Comment ' + (index + 1) + ', page ' + annotation.pageNumber" :class="selectedAnnotationId === annotation.id ? (annotation.feedbackSource === 'co_evaluator' ? 'border-blue-400 ring-1 ring-blue-200' : 'border-red-400 ring-1 ring-red-200') : 'border-gray-200 dark:border-gray-700'" class="rounded-xl border bg-white p-3 focus:outline-none focus:ring-2 focus:ring-red-500 dark:bg-gray-900">
                                    <div class="flex items-center gap-2">
                                        <span :style="{ backgroundColor: annotation.feedbackSource === 'co_evaluator' ? '#1d4ed8' : '#b91c1c' }" class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full  text-xs font-bold text-white" x-text="index + 1"></span>
                                        <button type="button" @click.stop="jumpToAnnotation(annotation)" class="text-xs font-semibold text-gray-600 dark:text-gray-300">Page <span x-text="annotation.pageNumber"></span></button>
                                        <div class="relative ml-auto" x-show="canAnnotate && annotation.canEdit && annotation.state === 'draft'" @keydown.escape.stop.prevent="commentMenuId = null">
                                            <button type="button" :aria-label="'Options for comment ' + (index + 1)" :aria-expanded="commentMenuId === annotation.id" @click.stop="commentMenuId = commentMenuId === annotation.id ? null : annotation.id" class="rounded-lg px-2 py-1 text-gray-500 hover:bg-gray-100">⋯</button>
                                            <div x-show="commentMenuId === annotation.id" x-cloak @click.outside="if (commentMenuId === annotation.id) commentMenuId = null" class="absolute right-0 top-full z-10 w-36 rounded-lg border border-gray-200 bg-white p-1 shadow-lg dark:border-gray-700 dark:bg-gray-900">
                                                <button data-edit-annotation type="button" @click.stop="editAnnotation(annotation)" :disabled="!!draftSelection || saving" class="block w-full rounded px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 disabled:opacity-40 dark:text-gray-200">Edit</button>
                                                <button data-remove-annotation type="button" @click.stop="deleteAnnotation(annotation)" :disabled="saving || !!deletingAnnotationId" class="block w-full rounded px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50 disabled:opacity-40 dark:text-red-300">Delete</button>
                                            </div>
                                        </div>
                                    </div>
                                    <blockquote x-show="annotation.selectedText" class="mt-2 line-clamp-3 border-l-2 border-red-200 pl-2 text-xs leading-5 text-gray-500 dark:text-gray-400" x-text="annotation.selectedText"></blockquote>
                                    <p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-gray-800 dark:text-gray-100" x-text="annotation.comment"></p>
                                    <p x-show="annotation.editorTargetLabel" class="mt-2 text-xs text-gray-500" x-text="annotation.editorTargetLabel"></p>
                                    <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400"><span class="font-semibold" x-text="annotation.feedbackLabel || 'Research Head'"></span><span x-show="annotation.feedbackSource !== 'co_evaluator'"> · <span x-text="annotation.reviewer"></span></span><span class="block" x-show="annotation.feedbackSource === 'co_evaluator'">Entered by <span x-text="annotation.reviewer"></span> · Research Head</span><span class="block" x-text="annotation.createdAt"></span></p>
                                    <p x-show="annotation.state !== 'draft'" class="mt-2 text-xs text-gray-500" x-text="annotation.state === 'resolved' ? 'Resolved by a new version' : 'Sent · locked'"></p>
                                    <a x-show="!isResearchHead && annotation.state === 'requested' && revisionUrl" :href="annotationEditUrl(annotation)" @click.stop class="mt-3 inline-flex rounded-lg bg-red-700 px-3 py-2 text-xs font-semibold text-white">
                                        <span x-text="annotation.editorTargetLabel ? 'Edit ' + annotation.editorTargetLabel : 'Revise this paper'"></span>
                                    </a>
                                </article>
                            </template>
                            <p x-show="reviewerCommentCount === 0" class="py-8 text-center text-sm leading-6 text-gray-500 dark:text-gray-400">No comments from this reviewer yet.</p>
                        </div>
                    </aside>
                </div>

                @if ($canAnnotate)
                    <section x-ref="commentComposer" x-show="draftSelection" x-cloak role="dialog" aria-labelledby="revision-comment-title" @keydown.escape.stop.prevent="cancelDraft()" @keydown.ctrl.enter.prevent="saveAnnotation()" @keydown.meta.enter.prevent="saveAnnotation()" class="fixed z-[120] w-[352px] max-w-[calc(100vw-1.5rem)] overflow-y-auto overscroll-contain rounded-2xl border border-gray-200 bg-white p-4 shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 id="revision-comment-title" class="text-sm font-semibold text-gray-900 dark:text-white" x-text="editingAnnotationId ? 'Edit comment' : 'Revision comment'"></h3>
                                <p class="mt-1 text-xs text-gray-500">Page <span x-text="draftSelection?.pageNumber"></span> · <span x-text="draftSelection?.type === 'pin' ? 'Pinned location' : 'Highlighted location'"></span></p>
                            </div>
                            <button type="button" @click="cancelDraft()" :disabled="saving" aria-label="Close comment editor" class="rounded px-2 text-xl text-gray-500 disabled:opacity-40">×</button>
                        </div>
                        <p class="mt-2 text-xs font-semibold text-gray-700 dark:text-gray-200" x-text="draftFeedbackSource === 'co_evaluator' ? 'Co-evaluator · ' + (draftCoEvaluatorName || 'Name required') : 'Research Head · ' + (config.researchHeadName || '')"></p>
                        <label x-show="draftFeedbackSource === 'co_evaluator' && !coEvaluatorName.trim()" class="mt-2 block text-xs text-gray-600 dark:text-gray-300">Co-evaluator's name
                            <input x-model="draftCoEvaluatorName" :disabled="saving" maxlength="160" class="mt-1 block w-full rounded-lg border-gray-300 text-xs dark:border-gray-700 dark:bg-gray-950 dark:text-white">
                        </label>
                        <p x-show="draftSectionLabel" x-text="draftSectionLabel" class="mt-2 text-xs font-semibold text-red-700 dark:text-red-300"></p>
                        <blockquote x-show="draftSelection?.selectedText" class="mt-3 max-h-16 overflow-auto border-l-2 border-red-200 pl-2 text-xs leading-5 text-gray-500" x-text="draftSelection?.selectedText"></blockquote>
                        <label class="mt-3 block text-sm font-medium text-gray-700 dark:text-gray-200">What needs to change?
                            <textarea x-ref="commentInput" x-model="draftComment" :disabled="saving" rows="3" maxlength="5000" class="mt-2 block w-full resize-y rounded-xl border-gray-300 text-sm focus:border-red-500 focus:ring-red-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white" placeholder="Describe the revision needed…"></textarea>
                        </label>
                        <p x-show="saveError" role="alert" class="mt-2 text-xs text-red-700 dark:text-red-300" x-text="saveError"></p>
                        <div class="mt-4 flex justify-end gap-2">
                            <button type="button" @click="cancelDraft()" :disabled="saving" class="rounded-lg px-3 py-2 text-sm text-gray-600 disabled:opacity-40 dark:text-gray-300">Cancel</button>
                            <button type="button" @click="saveAnnotation()" :disabled="saving || !draftComment.trim()" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40" x-text="saving ? 'Saving…' : (editingAnnotationId ? 'Save changes' : 'Add comment')"></button>
                        </div>
                    </section>
                @endif
            </div>
        </div>
    @endif
</x-app-layout>
