@props(['label', 'value', 'description' => null, 'icon', 'href' => null, 'action' => 'View', 'priority' => false, 'selected' => false])

<div @class(['dashboard-metric flex flex-col gap-3', 'dashboard-metric-priority' => $priority])>
    <dt class="flex items-start justify-between gap-2 text-sm font-semibold">
        <span>{{ $label }}</span>
        <x-report-library-icon :name="$icon" :class="$priority ? '!h-5 !w-5 text-rose-100' : '!h-5 !w-5 text-brand dark:text-rose-300'" />
    </dt>
    @if ($description)
        <dd @class(['-mt-2 text-sm leading-5', 'text-rose-100' => $priority, 'rh-muted' => ! $priority])>{{ $description }}</dd>
    @endif
    <dd class="mt-auto flex flex-wrap items-end justify-between gap-x-3 gap-y-2">
        <strong class="text-4xl font-bold tracking-tight tabular-nums">{{ $value }}</strong>
        @if ($href)
            <a href="{{ $href }}" @if ($selected) aria-current="true" @endif {{ $attributes->class(['secondary-action', 'ring-2 ring-brand dark:ring-rose-300' => $selected]) }}>
                {{ $action }} <span class="sr-only">({{ $label }})</span>
                <x-report-library-icon name="open" class="!h-4 !w-4" />
            </a>
        @endif
    </dd>
</div>
