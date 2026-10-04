<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'id' => null,
    'idExpression' => null,
    'name' => null,
    'nameExpression' => null,
    'value' => null,
    'model' => null,
    'min' => null,
    'max' => null,
    'required' => false,
    'placeholder' => 'Select date',
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'id' => null,
    'idExpression' => null,
    'name' => null,
    'nameExpression' => null,
    'value' => null,
    'model' => null,
    'min' => null,
    'max' => null,
    'required' => false,
    'placeholder' => 'Select date',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $initialValue = $model ? null : ($name ? old($name, $value) : $value);
?>

<div
    x-data="datePicker({
        initialValue: <?php echo \Illuminate\Support\Js::from($initialValue)->toHtml() ?>,
        min: <?php echo \Illuminate\Support\Js::from($min)->toHtml() ?>,
        max: <?php echo \Illuminate\Support\Js::from($max)->toHtml() ?>,
        required: <?php echo \Illuminate\Support\Js::from((bool) $required)->toHtml() ?>,
    })"
    <?php if($model): ?> x-modelable="value" x-model="<?php echo e($model); ?>" <?php endif; ?>
    x-on:click.outside="close"
    <?php echo e($attributes->class(['relative'])); ?>

>
    <input
        type="hidden"
        <?php if($name): ?> name="<?php echo e($name); ?>" <?php endif; ?>
        <?php if($nameExpression): ?> x-bind:name="<?php echo e($nameExpression); ?>" <?php endif; ?>
        x-model="value"
    >

    <div class="relative">
        <input
            x-ref="display"
            type="text"
            <?php if($id): ?> id="<?php echo e($id); ?>" <?php endif; ?>
            <?php if($idExpression): ?> x-bind:id="<?php echo e($idExpression); ?>" <?php endif; ?>
            x-bind:value="formattedValue"
            x-on:focus="handleFocus"
            x-on:click="open"
            x-on:beforeinput.prevent
            x-on:paste.prevent
            x-on:keydown.enter.prevent="open"
            x-on:keydown.space.prevent="open"
            x-on:keydown.arrow-down.prevent="open"
            x-on:keydown.escape.prevent.stop="close"
            x-on:keydown.tab="close"
            x-bind:aria-controls="panelId"
            x-bind:aria-expanded="isOpen"
            aria-haspopup="dialog"
            aria-autocomplete="none"
            aria-required="<?php echo e($required ? 'true' : 'false'); ?>"
            role="combobox"
            inputmode="none"
            autocomplete="off"
            placeholder="<?php echo e($placeholder); ?>"
            <?php if($required): echo 'required'; endif; ?>
            class="block w-full cursor-pointer rounded-xl border-gray-300 bg-white py-2.5 pl-3 pr-11 text-sm text-gray-900 shadow-sm transition hover:border-gray-400 focus:border-red-600 focus:ring-red-600"
        >
        <button
            type="button"
            x-on:click="toggle"
            class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-xl text-gray-400 transition hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-red-600"
            aria-label="Open calendar"
            tabindex="-1"
        >
            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5M5.25 5.25h13.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5Z" />
            </svg>
        </button>
    </div>

    <div
        x-cloak
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="translate-y-1 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-1 opacity-0"
        x-bind:id="panelId"
        x-on:keydown.escape.prevent.stop="closeAndFocus"
        role="dialog"
        aria-label="Choose a date"
        class="absolute left-0 z-50 mt-2 w-[calc(100vw-2rem)] max-w-sm origin-top-left rounded-2xl border border-gray-200 bg-white p-4 shadow-2xl sm:w-80"
    >
        <div class="flex items-center gap-2">
            <button
                type="button"
                x-on:click="changeMonth(-1)"
                x-bind:disabled="!canChangeMonth(-1)"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-30"
                aria-label="Previous month"
            >
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg>
            </button>

            <div class="grid min-w-0 flex-1 grid-cols-[minmax(0,1fr)_5.5rem] gap-2">
                <label class="sr-only" x-bind:for="monthSelectId">Calendar month</label>
                <select
                    x-bind:id="monthSelectId"
                    x-model.number="viewMonth"
                    class="min-w-0 rounded-xl border-gray-200 py-2 pl-3 pr-8 text-sm font-bold text-gray-900 focus:border-red-600 focus:ring-red-600"
                    aria-label="Calendar month"
                >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $monthIndex => $month): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <option value="<?php echo e($monthIndex); ?>"><?php echo e($month); ?></option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>

                <label class="sr-only" x-bind:for="yearInputId">Calendar year</label>
                <input
                    x-bind:id="yearInputId"
                    x-model.number="viewYear"
                    x-on:change="clampViewYear"
                    type="number"
                    inputmode="numeric"
                    x-bind:min="minYear"
                    x-bind:max="maxYear"
                    class="min-w-0 rounded-xl border-gray-200 px-2 py-2 text-center text-sm font-bold text-gray-900 focus:border-red-600 focus:ring-red-600"
                    aria-label="Calendar year"
                >
            </div>

            <button
                type="button"
                x-on:click="changeMonth(1)"
                x-bind:disabled="!canChangeMonth(1)"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 text-gray-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-700 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-30"
                aria-label="Next month"
            >
                <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6" /></svg>
            </button>
        </div>

        <p class="mt-2 text-center text-[11px] font-medium text-gray-500">Choose a month or type a year to jump directly.</p>

        <div class="mt-3 grid grid-cols-7 gap-1 text-center" aria-hidden="true">
            <template x-for="weekday in weekdays" x-bind:key="weekday">
                <span class="py-1 text-[10px] font-black uppercase tracking-wide text-gray-400" x-text="weekday"></span>
            </template>
        </div>

        <div class="mt-1 grid grid-cols-7 gap-1" role="grid" aria-label="Calendar days">
            <template x-for="day in calendarDays" x-bind:key="day.iso">
                <button
                    type="button"
                    x-on:click="selectDate(day.iso)"
                    x-bind:disabled="!day.selectable"
                    x-bind:aria-label="day.label"
                    x-bind:aria-current="day.isToday ? 'date' : null"
                    x-bind:aria-pressed="day.isSelected"
                    x-bind:class="{
                        'bg-red-600 text-white shadow-sm hover:bg-red-700': day.isSelected,
                        'text-gray-900 hover:bg-red-50 hover:text-red-700': day.isCurrentMonth && !day.isSelected && day.selectable,
                        'text-gray-300 hover:bg-gray-50': !day.isCurrentMonth && !day.isSelected && day.selectable,
                        'ring-1 ring-inset ring-red-300': day.isToday && !day.isSelected,
                        'cursor-not-allowed text-gray-300 opacity-40': !day.selectable,
                    }"
                    class="inline-flex h-9 w-full items-center justify-center rounded-lg text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-1"
                    x-text="day.dayNumber"
                ></button>
            </template>
        </div>

        <div class="mt-4 flex items-center justify-between gap-3 border-t border-gray-100 pt-3">
            <button
                x-show="!required && value"
                type="button"
                x-on:click="clear"
                class="rounded-lg px-2 py-1.5 text-xs font-bold text-gray-500 hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500"
            >Clear</button>
            <span x-show="required || !value" aria-hidden="true"></span>
            <button
                type="button"
                x-on:click="selectToday"
                x-bind:disabled="!todaySelectable"
                class="rounded-lg px-2 py-1.5 text-xs font-bold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600 disabled:cursor-not-allowed disabled:opacity-40"
            >Today</button>
        </div>
    </div>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/date-picker.blade.php ENDPATH**/ ?>