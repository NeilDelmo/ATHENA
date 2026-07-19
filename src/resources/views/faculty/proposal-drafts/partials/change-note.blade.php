<div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 sm:p-5">
    <label for="change-note" class="block text-xs font-black uppercase tracking-wider text-blue-950">What changed? <span class="font-semibold normal-case tracking-normal text-blue-700">(optional)</span></label>
    <textarea id="change-note" name="change_note" rows="2" maxlength="500" placeholder="For example: Updated the timeline after the team meeting." class="mt-2 block w-full rounded-xl border-blue-200 bg-white text-sm shadow-sm focus:border-blue-600 focus:ring-blue-600">{{ old('change_note') }}</textarea>
    <p class="mt-2 text-xs leading-5 text-blue-800">This note appears in version history. ATHENA also records an automatic summary of the fields or file that changed.</p>
</div>
