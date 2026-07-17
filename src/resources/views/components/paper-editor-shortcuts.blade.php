<section aria-label="Paper editor keyboard shortcuts" class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-blue-950">
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-wider">Editor shortcuts</p>
            <p class="mt-1 text-xs text-blue-800">Save, discard, or leave without hunting for the action buttons.</p>
        </div>
        <p class="text-[11px] font-semibold text-blue-700">Unsaved changes are confirmed before they are discarded.</p>
    </div>

    <dl class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (config('proposal_editor.shortcuts', []) as $shortcut)
            <div class="rounded-xl border border-blue-200 bg-white/80 p-3">
                <dt class="flex flex-wrap items-center gap-2">
                    <kbd class="rounded-md border border-blue-300 bg-white px-2 py-1 font-mono text-[10px] font-black text-blue-900 shadow-sm">{{ $shortcut['keys'] }}</kbd>
                    <span class="text-xs font-black">{{ $shortcut['action'] }}</span>
                </dt>
                <dd class="mt-1 text-[11px] leading-4 text-blue-800">{{ $shortcut['description'] }}</dd>
            </div>
        @endforeach
    </dl>
</section>
