@props(['title', 'description', 'href', 'icon', 'action'])

<article {{ $attributes->class(['dashboard-panel flex min-w-0 flex-col items-start gap-4 p-5']) }}>
    <div class="flex min-w-0 items-start gap-3">
        <span class="dashboard-icon"><x-report-library-icon :name="$icon" class="!h-5 !w-5" /></span>
        <div>
            <h2 class="dashboard-section-title">{{ $title }}</h2>
            <p class="mt-1 text-sm leading-5 rh-muted">{{ $description }}</p>
        </div>
    </div>
    <a href="{{ $href }}" class="secondary-action mt-auto">
        {{ $action }} <x-report-library-icon name="open" class="!h-4 !w-4" />
    </a>
</article>
