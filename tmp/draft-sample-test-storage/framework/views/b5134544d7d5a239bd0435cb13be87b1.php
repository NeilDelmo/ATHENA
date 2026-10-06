<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['topic']));

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

foreach (array_filter((['topic']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->canUseWorkspace(\App\Models\User::WORKSPACE_FACULTY_RESEARCHER)): ?>
    <form method="POST" action="<?php echo e(route('workspace.store')); ?>" data-research-workspace-switch>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="workspace" value="faculty_researcher">
        <input type="hidden" name="research_topic_id" value="<?php echo e($topic->id); ?>">
        <button type="submit" class="dashboard-action focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">Switch to Faculty Researcher</button>
    </form>
<?php else: ?>
    <p class="max-w-sm text-sm leading-5 text-slate-500 dark:text-slate-400">Ask the Research Office to enable your Faculty Researcher access.</p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/research-workspace-switch.blade.php ENDPATH**/ ?>