@props([
    'title' => null,
    'subtitle' => null,
    'container' => false,
    'variant' => 'default',
    'eyebrow' => null,
])

@if ($container)
    <header data-page-header-container {{ $attributes->class(['athena-page-header relative overflow-hidden border-b border-gray-100 bg-white transition-colors duration-300 dark:border-slate-800 dark:bg-slate-900']) }}>
        <div class="relative z-[1] w-full py-6 px-4 sm:px-6 lg:px-8">
            {{ $slot }}
        </div>
    </header>
@elseif (in_array($variant, ['hero', 'banner'], true))
    <div data-page-header-variant="{{ $variant }}" data-workspace-header-banner {{ $attributes->class(['flex flex-col gap-4 border-l-4 border-[#800000] !bg-transparent px-5 py-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between dark:!bg-transparent']) }}>
        <div class="min-w-0 sm:min-w-[min(100%,16rem)] sm:flex-1">
            <p class="text-sm font-bold uppercase tracking-[0.2em] text-[#800000] dark:text-red-300">{{ $eyebrow }}</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl dark:text-white">{{ $title }}</h2>
            <p class="mt-1 max-w-3xl text-base leading-6 text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
        </div>

        @isset($actions)
            <div class="max-w-full shrink-0">{{ $actions }}</div>
        @endisset
    </div>
@else
    <div data-page-header-variant="simple" {{ $attributes->class(['flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between']) }}>
        <div class="min-w-0 sm:min-w-[min(100%,16rem)] sm:flex-1">
            <h1 class="text-3xl font-black tracking-tight text-slate-950 dark:text-white">{{ $title }}</h1>

            @if ($subtitle)
                <p class="mt-1 text-base leading-6 text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex w-full max-w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center sm:justify-end">
                {{ $actions }}
            </div>
        @endisset
    </div>
@endif
