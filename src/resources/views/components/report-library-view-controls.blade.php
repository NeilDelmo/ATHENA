<p {{ $attributes->merge(['class' => 'text-base leading-6 text-slate-500 dark:text-slate-400']) }}>Ctrl + scroll to resize · Right-click for View</p>
<template x-teleport="body">
    <div x-ref="libraryViewMenu" x-cloak x-show="viewMenuOpen" role="menu" aria-label="View"
        :style="{ left: viewMenuX + 'px', top: viewMenuY + 'px' }"
        @click.stop @click.outside="closeViewMenu(false)" @keydown.stop="handleViewMenuKey($event)"
        @scroll.window.capture="closeViewMenu(false)" @resize.window="closeViewMenu(false)" @open-modal.window="closeViewMenu(false)"
        class="fixed z-[200] w-60 max-w-[calc(100vw-1rem)] rounded-lg border border-slate-200 bg-white p-1.5 text-base text-slate-900 shadow-lg dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
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
