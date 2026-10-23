@props(['sortOptions' => [], 'showTriggerIcon' => false])

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2 text-base leading-6 text-slate-500 dark:text-slate-400']) }}>
    <span class="hidden sm:inline">Ctrl + scroll to resize · Right-click for actions</span>
    <button type="button" class="workspace-secondary" aria-haspopup="menu" :aria-expanded="viewMenuOpen" :disabled="$data.saving || $data.stale" @click="openViewMenu($event)">@if ($showTriggerIcon)<x-report-library-icon name="actions" class="!h-5 !w-5" />@endif<span x-text="menuLabel === 'View' ? 'View' : 'Actions'"></span></button>
</div>
<template x-teleport="body">
    <div x-ref="libraryViewMenu" x-cloak x-show="viewMenuOpen" role="menu" aria-label="View" :aria-label="menuLabel"
        :style="{ left: viewMenuX + 'px', top: viewMenuY + 'px' }"
        @click.stop @click.outside="closeViewMenu(false)" @keydown.stop="handleViewMenuKey($event)"
        @scroll.window.capture="if (viewMenuReady && !$refs.libraryViewMenu.contains($event.target)) closeViewMenu(false)" @resize.window="closeViewMenu(false)" @open-modal.window="closeViewMenu(false)"
        class="fixed z-[200] max-h-[calc(100dvh-1rem)] w-64 max-w-[calc(100vw-1rem)] overflow-y-auto rounded-lg border border-slate-200 bg-white p-1.5 text-base text-slate-900 shadow-lg dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
        <template x-for="action in menuActions" :key="action.id">
            <div>
                <template x-if="!action.href">
                    <button type="button" role="menuitem" :disabled="action.disabled || $data.saving || $data.stale" :aria-keyshortcuts="action.shortcut?.replace('Ctrl+', 'Control+') || null" @click="activateMenuAction(action)"
                        class="flex min-h-11 w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left hover:bg-slate-100 focus-visible:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-40 dark:hover:bg-slate-800 dark:focus-visible:bg-slate-800">
                        <span class="flex items-center gap-3"><x-report-library-action-icon /><span x-text="action.label"></span></span><span x-show="action.shortcut" aria-hidden="true" class="text-sm text-slate-500 dark:text-slate-400" x-text="action.shortcut"></span>
                    </button>
                </template>
                <template x-if="action.href">
                    <a role="menuitem" :href="action.href" :download="action.download || null" @click="closeViewMenu()" class="flex min-h-11 w-full items-center gap-3 rounded-md px-3 py-2 hover:bg-slate-100 focus-visible:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:hover:bg-slate-800 dark:focus-visible:bg-slate-800"><x-report-library-action-icon /><span x-text="action.label"></span></a>
                </template>
            </div>
        </template>
        <div x-show="menuActions.length" role="separator" class="my-1 border-t border-slate-200 dark:border-slate-700"></div>
        @if ($sortOptions)
            <p class="px-3 py-2 font-semibold" role="presentation">Sort by</p>
            @foreach ($sortOptions as $field => $label)
                <button type="button" role="menuitemradio" :aria-checked="sortKey === '{{ $field }}'" :disabled="saving || stale" @click="setSort('{{ $field }}')" class="flex min-h-11 w-full items-center gap-3 rounded-md px-3 py-2 text-left hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:opacity-50 dark:hover:bg-slate-800">
                    <x-report-library-icon name="sort-asc" class="!h-5 !w-5" /><span class="flex-1">{{ $label }}</span><span x-show="sortKey === '{{ $field }}'" aria-hidden="true" x-text="sortDirection === 'asc' ? '↑' : '↓'"></span>
                </button>
            @endforeach
            <div role="separator" class="my-1 border-t border-slate-200 dark:border-slate-700"></div>
        @endif
        <p class="px-3 py-2 font-semibold" role="presentation">View</p>
        @foreach (['small' => 'Small icons', 'medium' => 'Medium icons', 'large' => 'Large icons', 'list' => 'Details'] as $option => $label)
            @php($active = $option === 'list' ? "view === 'list'" : "view === 'grid' && size === '".$option."'")
            <button type="button" role="menuitemradio" data-view-option="{{ $option }}" :aria-checked="{{ $active }}" :disabled="$data.saving || $data.stale" @click="chooseViewOption('{{ $option }}')"
                class="flex min-h-11 w-full items-center gap-3 rounded-md px-3 py-2 text-left hover:bg-slate-100 focus-visible:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand disabled:opacity-50 dark:hover:bg-slate-800 dark:focus-visible:bg-slate-800">
                <x-report-library-icon :name="$option === 'list' ? 'list' : 'grid'" class="!h-5 !w-5" />
                <span class="flex-1">{{ $label }}</span>
                <svg x-show="{{ $active }}" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6" /></svg>
            </button>
        @endforeach
    </div>
</template>
