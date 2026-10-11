@props([
    'id',
    'label',
    'model',
    'options' => [],
    'optionsExpression' => null,
    'placeholder',
    'searchPlaceholder',
    'disabled' => false,
    'describedBy' => null,
])

<div
    x-data="{
        open: false, query: '', activeIndex: 0,
        get value() { return {{ $model }}; },
        get choices() {
            return [{ value: '', label: @js($placeholder) }, ...({{ $optionsExpression ?? Illuminate\Support\Js::from($options) }})];
        },
        get filteredOptions() {
            return this.choices.filter(option => [option.label, option.description].join(' ').toLowerCase().includes(this.query.trim().toLowerCase()));
        },
        get selected() { return this.choices.find(option => option.value === this.value) || this.choices[0]; },
        toggle() {
            this.open = !this.open;
            this.query = '';
            this.activeIndex = Math.max(0, this.choices.findIndex(option => option.value === this.value));
            if (this.open) this.$nextTick(() => { this.$refs.search.focus(); this.scrollActive(); });
        },
        move(step) {
            if (!this.filteredOptions.length) return;
            this.activeIndex = (this.activeIndex + step + this.filteredOptions.length) % this.filteredOptions.length;
            this.scrollActive();
        },
        scrollActive() {
            this.$nextTick(() => this.$refs.list.querySelectorAll('[role=option]')[this.activeIndex]?.scrollIntoView({ block: 'nearest' }));
        },
        choose(option) {
            if (!option) return;
            {{ $model }} = option.value;
            this.open = false;
            this.$dispatch('change');
            this.$refs.trigger.focus();
        },
    }"
    @click.outside="open = false"
    @keydown.escape.stop.prevent="open = false; $refs.trigger.focus()"
    @focusout="$nextTick(() => { if (!$el.contains(document.activeElement)) open = false; })"
    :class="{ 'z-30': open }"
    {{ $attributes->class(['relative min-w-0']) }}
>
    <button
        id="{{ $id }}"
        x-ref="trigger"
        type="button"
        @click="toggle()"
        @keydown.arrow-down.prevent="if (!open) toggle()"
        @keydown.arrow-up.prevent="if (!open) toggle()"
        :aria-expanded="open"
        aria-haspopup="dialog"
        aria-controls="{{ $id }}-menu"
        aria-label="{{ $label }}"
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        :title="selected.label"
        @disabled($disabled)
        class="flex min-h-11 w-full items-center gap-3 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-left text-sm text-slate-700 transition-colors hover:border-slate-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-slate-500 dark:focus-visible:ring-offset-slate-900 dark:disabled:bg-slate-800 dark:disabled:text-slate-400"
    >
        <span class="min-w-0 flex-1">
            <span class="block line-clamp-2 break-words font-medium leading-5" x-text="selected.label"></span>
            <span x-show="selected.description" class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400" x-text="selected.description"></span>
        </span>
        <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" :d="open ? 'm6 15 6-6 6 6' : 'm6 9 6 6 6-6'" /></svg>
    </button>

    <div id="{{ $id }}-menu" x-show="open" x-cloak role="dialog" aria-label="{{ $label }}" class="absolute -right-4 top-full z-30 mt-2 w-[calc(100%+2rem)] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900 sm:right-0 sm:w-full sm:min-w-[18rem]">
        <div class="border-b border-slate-100 p-3 dark:border-slate-800">
            <div class="flex items-center gap-2 rounded-lg bg-slate-100 px-3 focus-within:ring-2 focus-within:ring-red-500 dark:bg-slate-800">
                <svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5" /><path stroke-linecap="round" d="m16 16 4 4" /></svg>
                <input
                    x-ref="search"
                    type="search"
                    x-model="query"
                    @input="activeIndex = 0"
                    @change.stop
                    @keydown.arrow-down.prevent="move(1)"
                    @keydown.arrow-up.prevent="move(-1)"
                    @keydown.enter.prevent="choose(filteredOptions[activeIndex])"
                    @keydown.tab="open = false"
                    role="combobox"
                    aria-label="{{ $searchPlaceholder }}"
                    aria-autocomplete="list"
                    aria-controls="{{ $id }}-list"
                    :aria-expanded="open"
                    :aria-activedescendant="filteredOptions[activeIndex] ? '{{ $id }}-option-' + activeIndex : null"
                    placeholder="{{ $searchPlaceholder }}"
                    autocomplete="off"
                    class="min-h-11 min-w-0 w-full border-0 bg-transparent px-0 text-sm text-slate-900 placeholder:text-slate-500 focus:ring-0 dark:text-white dark:placeholder:text-slate-400"
                >
            </div>
        </div>
        <div id="{{ $id }}-list" x-ref="list" role="listbox" aria-label="{{ $label }}" class="max-h-72 overflow-y-auto overscroll-contain p-1.5">
            <template x-for="(option, index) in filteredOptions" :key="option.value">
                <button
                    :id="'{{ $id }}-option-' + index"
                    type="button"
                    role="option"
                    tabindex="-1"
                    :aria-selected="value === option.value"
                    @click="choose(option)"
                    @mousemove="activeIndex = index"
                    :class="{ 'bg-slate-100 dark:bg-slate-800': activeIndex === index && value !== option.value }"
                    class="flex min-h-11 w-full items-start gap-3 rounded-lg px-3 py-3 text-left text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-red-500 aria-selected:bg-red-50 aria-selected:text-[#7A0019] dark:text-slate-200 dark:aria-selected:bg-red-950/40 dark:aria-selected:text-red-200"
                >
                    <span class="min-w-0 flex-1">
                        <span class="block whitespace-normal break-words text-sm font-medium leading-6" x-text="option.label"></span>
                        <span x-show="option.description" class="mt-1 block whitespace-normal break-words text-xs leading-5 text-slate-500 dark:text-slate-400" x-text="option.description"></span>
                    </span>
                    <svg x-show="value === option.value" class="mt-1 h-4 w-4 shrink-0 text-[#7A0019] dark:text-red-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
                </button>
            </template>
        </div>
        <p x-show="!filteredOptions.length" role="status" class="px-5 py-8 text-center text-sm leading-6 text-slate-500 dark:text-slate-400">No matches. Try a different search.</p>
    </div>
</div>
