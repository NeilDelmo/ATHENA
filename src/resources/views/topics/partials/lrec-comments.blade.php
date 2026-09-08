<section x-show="decision === 'revision_requested'" x-cloak class="space-y-3" x-data="{ comments: @js(old('committee_comments', [])) }">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h4 class="text-sm font-semibold text-gray-950 dark:text-white">LREC comments</h4>
        <button type="button" @click="comments.push({ reviewer: comments.length ? comments[comments.length - 1].reviewer : '', location: '', comment: '' })" class="text-sm font-semibold text-red-700 dark:text-red-300">+ Add comment</button>
    </div>
    <p class="text-xs text-gray-600 dark:text-slate-300">Record comments from any committee member, or use “LREC committee” for a shared recommendation. Select affected documents below when faculty must change a file.</p>
    <template x-for="(item, index) in comments" :key="index">
        <div class="space-y-2 rounded-lg border border-gray-200 p-3 dark:border-slate-700">
            <div class="flex items-center justify-between gap-2"><span class="text-xs font-semibold" x-text="'Comment ' + (index + 1)"></span><button type="button" @click="comments.splice(index, 1)" class="text-xs text-red-700 dark:text-red-300">Remove</button></div>
            <label class="block text-xs font-medium">Reviewer name<input :name="'committee_comments[' + index + '][reviewer]'" x-model="item.reviewer" :disabled="decision !== 'revision_requested'" :required="decision === 'revision_requested'" maxlength="160" placeholder="Name or LREC committee" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
            <label class="block text-xs font-medium">Comment<textarea :name="'committee_comments[' + index + '][comment]'" x-model="item.comment" :disabled="decision !== 'revision_requested'" :required="decision === 'revision_requested'" maxlength="5000" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea></label>
            <label class="block text-xs font-medium">Document or section <span class="font-normal text-gray-500">(optional)</span><input :name="'committee_comments[' + index + '][location]'" x-model="item.location" :disabled="decision !== 'revision_requested'" maxlength="300" placeholder="For example: Detailed Proposal, V. Rationale, page 2" class="mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
        </div>
    </template>
    @error('committee_comments')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
</section>
