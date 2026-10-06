@props(['item', 'documentType' => '', 'documentTypes' => collect()])

@php
    $noChange = (bool) old('feedback_responses.'.$item['key'].'.no_change', $item['no_change'] ?? false);
@endphp

<div data-revision-response-key="{{ $item['key'] }}" data-revision-response-source="{{ $item['form_source'] ?? '' }}" class="space-y-3">
    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
        Your response <span aria-hidden="true" class="text-brand dark:text-rose-300">*</span><span class="sr-only"> (required)</span>
        <textarea data-revision-response-document="{{ $documentType }}" name="feedback_responses[{{ $item['key'] }}][response]" rows="4" maxlength="5000" required placeholder="Explain your change or why the submitted paper already addresses this comment." class="mt-2 block w-full rounded-lg border-slate-300 text-sm leading-6 focus:border-[#7A0019] focus:ring-[#7A0019] dark:border-slate-600 dark:bg-slate-950 dark:text-white">{{ old('feedback_responses.'.$item['key'].'.response', $item['response'] ?? '') }}</textarea>
    </label>
    <fieldset data-comment-response-location data-response-key="{{ $item['key'] }}" data-response-document="{{ $documentType }}" aria-label="Automatic change location" class="space-y-2">
        <input type="hidden" name="feedback_responses[{{ $item['key'] }}][no_change]" value="0">
        <input type="checkbox" data-comment-response-action data-comment-response-no-change name="feedback_responses[{{ $item['key'] }}][no_change]" value="1" hidden tabindex="-1" @checked($noChange)>
        <input type="hidden" data-location-document value="{{ $documentType }}">
        <input type="hidden" data-comment-response-page name="feedback_responses[{{ $item['key'] }}][page]" value="{{ old('feedback_responses.'.$item['key'].'.page', $item['page'] ?? '') }}" @disabled($noChange)>
        <input type="hidden" data-comment-response-paragraph name="feedback_responses[{{ $item['key'] }}][paragraph]" value="{{ old('feedback_responses.'.$item['key'].'.paragraph', $item['paragraph'] ?? '') }}" @disabled($noChange)>
        <p data-location-status role="status" aria-live="polite" class="text-xs leading-5 text-slate-500 dark:text-slate-400">The page and paragraph will be added automatically when you finish this paper.</p>
        <button type="button" data-location-retry hidden class="revision-location-button">Try again</button>
        <div data-location-confirm hidden aria-label="Choose the updated passage" class="revision-location-confirm"></div>
    </fieldset>
    <button type="button" data-comment-response-preview-open="{{ $item['form_source'] ?? 'research_head' }}" aria-haspopup="dialog" class="revision-location-button">Preview Comment Response</button>
</div>
