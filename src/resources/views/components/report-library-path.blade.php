@props(['crumbs', 'backUrl' => null])

<div class="flex min-w-0 flex-wrap items-center gap-3 border-b border-slate-200 p-4 dark:border-slate-700">
    <div class="flex min-w-0 flex-1 basis-full items-center gap-2 sm:basis-0">
        @if ($backUrl)
            <a href="{{ $backUrl }}" class="workspace-icon focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-label="Up one folder" title="Up one folder"><x-report-library-icon name="back" /></a>
        @endif
    <nav aria-label="Folder path" class="flex min-h-11 min-w-0 flex-1 flex-wrap items-center gap-2 text-base leading-7">
        @foreach ($crumbs as $crumb)
            @unless ($loop->first)<span aria-hidden="true" class="text-slate-400">/</span>@endunless
            @if (isset($crumb['url']))
                <a href="{{ $crumb['url'] }}" class="rounded px-1 text-slate-600 hover:text-brand focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand dark:text-slate-300">{{ $crumb['label'] }}</a>
            @else
                <span aria-current="page" class="px-1 font-semibold text-slate-950 dark:text-white">{{ $crumb['label'] }}</span>
            @endif
        @endforeach
    </nav>
    </div>
    <div class="ml-auto flex shrink-0 items-center gap-2">{{ $slot }}</div>
</div>
