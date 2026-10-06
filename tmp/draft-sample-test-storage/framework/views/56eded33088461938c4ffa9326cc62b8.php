<div data-proposal-writing-toolbar data-proposal-workspace-toolbar :inert="previewFullscreen" class="proposal-writing-toolbar detailed-proposal-writing-toolbar" role="group" aria-label="Detailed proposal writing tools">
    <div class="proposal-writing-toolbar-row" role="toolbar" aria-label="Text formatting and insertion">
        <p class="proposal-writing-target">Editing: <span data-writing-target>Select a writing field</span></p>
        <div class="proposal-writing-buttons">
            <div class="proposal-writing-action-group" role="group" aria-label="Text style">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['bold' => 'Bold', 'italic' => 'Italic', 'underline' => 'Underline']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $command => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <button type="button" data-writing-command="<?php echo e($command); ?>" aria-pressed="false" disabled><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-icon','data' => ['name' => $command]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($command)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $attributes = $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $component = $__componentOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?><span><?php echo e($label); ?></span></button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
            <div class="proposal-writing-action-group" role="group" aria-label="Lists">
                <button type="button" data-writing-command="insertUnorderedList" aria-pressed="false" disabled><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-icon','data' => ['name' => 'bullets']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'bullets']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $attributes = $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $component = $__componentOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?><span>Bullets</span></button>
                <button type="button" data-writing-command="insertOrderedList" aria-pressed="false" disabled><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-icon','data' => ['name' => 'numbered-list']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'numbered-list']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $attributes = $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $component = $__componentOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?><span>Numbered list</span></button>
            </div>
            <div class="proposal-writing-action-group" role="group" aria-label="History">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['undo' => 'Undo', 'redo' => 'Redo']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $command => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <button type="button" data-writing-command="<?php echo e($command); ?>" disabled><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-icon','data' => ['name' => $command]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($command)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $attributes = $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $component = $__componentOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?><span><?php echo e($label); ?></span></button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
            <div class="proposal-writing-action-group" role="group" aria-label="Insert">
                <button type="button" data-writing-image disabled title="Select a narrative section that supports figures"><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-icon','data' => ['name' => 'image']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'image']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $attributes = $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $component = $__componentOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?><span>Image</span></button>
                <button type="button" data-writing-table aria-expanded="false" aria-controls="proposal-table-picker" disabled><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-icon','data' => ['name' => 'table']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'table']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $attributes = $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $component = $__componentOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?><span>Table</span></button>
                <button type="button" data-writing-cite disabled title="Select a passage in a narrative section"><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-icon','data' => ['name' => 'source']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'source']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $attributes = $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $component = $__componentOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?><span>Support with source</span></button>
            </div>
            <div class="proposal-writing-action-group" role="group" aria-label="Field view">
                <button type="button" data-writing-expand aria-pressed="false" disabled><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-icon','data' => ['name' => 'expand','class' => 'proposal-writing-expand-icon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'expand','class' => 'proposal-writing-expand-icon']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $attributes = $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $component = $__componentOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-icon','data' => ['name' => 'shrink','class' => 'proposal-writing-shrink-icon']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'shrink','class' => 'proposal-writing-shrink-icon']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $attributes = $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $component = $__componentOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?><span data-writing-expand-label>Expand field</span></button>
            </div>
        </div>
    </div>
    <div data-writing-table-tools hidden class="proposal-writing-buttons" role="toolbar" aria-label="Selected table tools">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['add-row' => 'Add row', 'add-column' => 'Add column', 'remove-row' => 'Remove row', 'remove-column' => 'Remove column', 'remove-table' => 'Remove table']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <button type="button" data-writing-table-action="<?php echo e($action); ?>"><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-icon','data' => ['name' => $action]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($action)]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $attributes = $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $component = $__componentOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?><span><?php echo e($label); ?></span></button>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>
    <p class="proposal-writing-hint">Select a writing field to use the tools. Add images with Image or drop them into a supported section. Figures appear before that section’s text.</p>
    <div id="proposal-table-picker" data-writing-table-picker hidden class="proposal-table-picker" role="group" aria-label="Insert a table">
        <label>Rows <input type="number" min="1" max="100" step="1" value="3" data-table-rows></label>
        <label>Columns <input type="number" min="1" max="12" step="1" value="2" data-table-columns></label>
        <label class="proposal-table-header-option"><input type="checkbox" checked data-table-header> Header row</label>
        <button type="button" data-writing-insert-table><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-icon','data' => ['name' => 'table']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'table']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $attributes = $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__attributesOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe)): ?>
<?php $component = $__componentOriginal7a9485575b48ddf8197622ac7859a0fe; ?>
<?php unset($__componentOriginal7a9485575b48ddf8197622ac7859a0fe); ?>
<?php endif; ?><span>Insert table</span></button>
    </div>
    <span data-writing-status class="sr-only" role="status" aria-live="polite"></span>
</div>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views/components/detailed-proposal-writing-toolbar.blade.php ENDPATH**/ ?>