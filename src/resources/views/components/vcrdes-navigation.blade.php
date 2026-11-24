@props(['linkClasses'])

@php
    $sections = [
        'Overview' => [['Overview', 'grid', '#overview']],
        'Projects' => [
            ['Research projects', 'folder-open', '#projects'],
            ['Needs attention', 'history', '#attention'],
        ],
        'Planning & reports' => [
            ['Budget utilization', 'presentation', '#budget'],
            ['College performance', 'college', '#colleges'],
        ],
    ];
    $activeClasses = '!bg-brand-wash !text-brand !font-semibold before:absolute before:left-0 before:top-1/2 before:h-5 before:w-1 before:-translate-y-1/2 before:rounded-r-full before:bg-brand dark:!bg-rose-950/40 dark:!text-rose-200';
@endphp

<nav
    data-vcrdes-navigation
    aria-label="VCRDES monitoring"
    class="space-y-4"
    x-data="{
        activeSection: '',
        syncSection() {
            const overview = this.$el.querySelector('a');
            this.activeSection = window.location.pathname === overview.pathname ? (window.location.hash || overview.hash) : '';
        },
    }"
    x-init="syncSection()"
    @hashchange.window="syncSection()"
    x-on:livewire:navigated.window="syncSection()"
>
    @foreach ($sections as $heading => $links)
        <section aria-label="{{ $heading }}">
            <h2 x-show="$store.sidebar.open" class="mb-2 flex items-center gap-3 px-4 text-xs font-semibold text-slate-500 dark:text-slate-400">
                {{ $heading }}<span class="h-px flex-1 bg-slate-200 dark:bg-slate-800" aria-hidden="true"></span>
            </h2>
            <div class="space-y-1">
                @foreach ($links as [$label, $icon, $anchor])
                    <a href="{{ route('vcrdes.dashboard').$anchor }}" aria-label="{{ $label }}" title="{{ $label }}"
                        class="{{ $linkClasses }}"
                        :aria-current="activeSection === @js($anchor) ? 'location' : null"
                        :class="[!$store.sidebar.open && '!justify-center !gap-0 !px-0', activeSection === @js($anchor) && @js($activeClasses)]"
                        @click="if (window.innerWidth < 640) $store.sidebar.setOpen(false)">
                        <x-report-library-icon :name="$icon" class="h-5 w-5 shrink-0" />
                        <span x-show="$store.sidebar.open" class="whitespace-nowrap">{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
</nav>
