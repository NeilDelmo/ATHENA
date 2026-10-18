@php
    $root = route('research_head.rms-quarterly-reports.index');
    $crumbs = [['label' => 'Quarterly monitoring', 'url' => $browsingYears ? null : $root]];
    if (! $browsingYears) { $crumbs[] = ['label' => $year]; }
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Quarterly monitoring" subtitle="Open a year and quarter to review the campus report.">
            <x-slot name="actions"><a href="{{ route('research_head.quarterly-reports.index') }}" class="workspace-icon focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-label="Open college reports" title="College reports"><x-report-library-icon name="college" /></a></x-slot>
        </x-page-header>
    </x-slot>
    <section data-report-library x-data="reportLibrary(@js('athena-report-library:'.auth()->id()))" data-view="grid" :data-view="view" data-size="medium" :data-size="size" class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
        <x-report-library-path :crumbs="$crumbs" :back-url="$browsingYears ? null : $root">
            <form method="GET" action="{{ $root }}" class="flex items-center gap-2">
                <input type="number" name="year" min="2000" max="2100" value="{{ $year }}" required aria-label="Reporting year" class="workspace-input w-28">
                <button type="submit" class="workspace-icon focus-visible:outline focus-visible:outline-2 focus-visible:outline-brand" aria-label="Open reporting year" title="Open reporting year"><x-report-library-icon name="calendar" /></button>
            </form>
        </x-report-library-path>
        <x-report-library-view-controls class="justify-end border-b border-slate-200 px-4 py-3 dark:border-slate-700" />
        <div data-library-view-surface tabindex="0" aria-label="Report folders and files" @wheel="resizeWithWheel($event)" @contextmenu="openViewMenu($event)" class="report-library-items min-h-[320px] p-5">
            @if ($browsingYears)
                @foreach ($years as $folderYear)
                    <x-report-library-folder :href="route('research_head.rms-quarterly-reports.index', ['year' => $folderYear])" :name="(string) $folderYear" description="Quarterly campus reports" />
                @endforeach
            @else
                @foreach ([1 => 'January–March', 2 => 'April–June', 3 => 'July–September', 4 => 'October–December'] as $folderQuarter => $months)
                    @php
                        $sourceCount = $collegeReports->get($folderQuarter, collect())->count();
                    @endphp
                    <x-report-library-folder :href="route('research_head.rms-quarterly-reports.index', ['year' => $year, 'quarter' => $folderQuarter])" :name="'Q'.$folderQuarter" :description="$months.' · '.$sourceCount.' '.Illuminate\Support\Str::plural('college report', $sourceCount).($drafts->has($folderQuarter) ? ' · Draft saved' : '')" />
                @endforeach
            @endif
        </div>
    </section>
</x-app-layout>
