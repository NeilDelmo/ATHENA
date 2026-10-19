@props(['papers', 'previousNumber', 'latestNumber'])

<div data-submitted-content-comparison class="divide-y divide-slate-200 border-t border-slate-200 dark:divide-slate-700 dark:border-slate-700">
    @forelse ($papers as $paper)
        <details class="group px-5 py-4">
            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 font-semibold text-slate-900 dark:text-white [&::-webkit-details-marker]:hidden">
                <span>{{ $paper['label'] }} <span class="ml-2 text-xs font-normal text-slate-500 dark:text-slate-400">{{ $paper['mode'] === 'file' ? 'File comparison' : count($paper['sections']).' changed '.\Illuminate\Support\Str::plural('section', count($paper['sections'])).' · '.count($paper['figures']).' figure '.\Illuminate\Support\Str::plural('change', count($paper['figures'])) }}</span></span>
                <span aria-hidden="true" class="text-brand group-open:rotate-90 dark:text-red-300">›</span>
            </summary>
            @if ($paper['mode'] === 'file')
                <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">This uploaded or older paper has no comparable structured content. The submitted file changed; use the saved versions below to review its contents.</p>
            @else
                @if ($paper['sections'] === [] && $paper['figures'] === [])
                    <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">The PDF changed, with no changes in the comparable saved fields.</p>
                @endif
                @foreach ($paper['sections'] as $section)
                    <section class="mt-4" aria-label="{{ $section['label'] }} comparison">
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $section['label'] }}</h4>
                        <div class="mt-2 grid gap-3 md:grid-cols-2">
                            @foreach (['before' => $previousNumber, 'after' => $latestNumber] as $side => $number)
                                <div class="min-w-0 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                                    <p class="mb-2 text-xs font-semibold text-slate-500 dark:text-slate-400">Version {{ $number }} · {{ $side === 'before' ? 'Previous' : 'Current' }}</p>
                                    <div class="max-h-80 space-y-2 overflow-y-auto break-words text-sm leading-6 text-slate-700 dark:text-slate-200" tabindex="0" aria-label="{{ $section['label'] }}, version {{ $number }}">
                                        @forelse ($section[$side] as $paragraph)
                                            <p @class(['whitespace-pre-wrap rounded px-2 py-1', 'bg-red-50 text-red-900 dark:bg-red-950/40 dark:text-red-200' => $paragraph['changed'] && $side === 'before', 'bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200' => $paragraph['changed'] && $side === 'after'])>@if ($paragraph['changed'])<span class="sr-only">{{ $side === 'before' ? 'Removed or replaced: ' : 'Added or revised: ' }}</span>@endif{{ $paragraph['text'] }}</p>
                                        @empty
                                            <p class="text-slate-500 dark:text-slate-400">No content in this version.</p>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
                @if ($paper['figures'] !== [])
                    <ul class="mt-4 divide-y divide-slate-100 text-sm leading-6 dark:divide-slate-800" aria-label="Submitted figure changes">
                        @foreach ($paper['figures'] as $figure)
                            <li class="py-3"><span class="font-semibold text-slate-900 dark:text-white">Figure {{ $figure['status'] }}</span>@if ($figure['before'])<p class="break-words text-slate-600 dark:text-slate-300">Previous: {{ $figure['before'] }}</p>@endif @if ($figure['after'])<p class="break-words text-slate-600 dark:text-slate-300">Current: {{ $figure['after'] }}</p>@endif</li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </details>
    @empty
        <p class="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">No comparable paper content or file changes between these submitted versions.</p>
    @endforelse
</div>
