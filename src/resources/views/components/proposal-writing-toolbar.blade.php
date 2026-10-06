@props(['toolbarId'])

<div data-proposal-workspace-toolbar :inert="previewFullscreen" :class="{ 'proposal-writing-toolbar-closed': !writingToolbarOpen }" {{ $attributes->class(['proposal-writing-toolbar']) }} role="group">
    <div class="proposal-writing-toolbar-heading">
        <div x-show="writingToolbarOpen" class="proposal-writing-toolbar-context">{{ $context }}</div>
        <button type="button" data-writing-toolbar-close x-show="writingToolbarOpen" @click="closeWritingToolbar()" aria-label="Hide tools" aria-controls="{{ $toolbarId }}-controls" :aria-expanded="writingToolbarOpen">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
        </button>
        <button type="button" data-writing-toolbar-open x-show="!writingToolbarOpen" x-cloak @click="showWritingToolbar()" aria-controls="{{ $toolbarId }}-controls" :aria-expanded="writingToolbarOpen">Show tools</button>
    </div>
    <div id="{{ $toolbarId }}-controls" data-writing-toolbar-controls x-show="writingToolbarOpen">{{ $slot }}</div>
</div>
