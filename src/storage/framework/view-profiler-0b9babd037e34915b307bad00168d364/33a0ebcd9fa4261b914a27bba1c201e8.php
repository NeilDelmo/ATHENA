<?php if (isset($component)) { $__componentOriginal90e243942d22f6a4722cd614e5732fed = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal90e243942d22f6a4722cd614e5732fed = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-toolbar','data' => ['toolbarId' => 'detailed-proposal-tools','toggle' => true,'dataProposalWritingToolbar' => true,'class' => 'detailed-proposal-writing-toolbar','ariaLabel' => 'Detailed proposal writing tools']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-toolbar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['toolbar-id' => 'detailed-proposal-tools','toggle' => true,'data-proposal-writing-toolbar' => true,'class' => 'detailed-proposal-writing-toolbar','aria-label' => 'Detailed proposal writing tools']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

     <?php $__env->slot('context', null, []); ?>  <?php $__env->endSlot(); ?>
    <div class="proposal-writing-toolbar-row" role="toolbar" aria-label="Text formatting and insertion">
        <p data-writing-guidance class="proposal-writing-guidance">Click a text field to use formatting.</p>

        <div class="proposal-writing-buttons">
            <div class="proposal-writing-action-group" role="group" aria-label="Text style">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['bold' => 'Bold', 'italic' => 'Italic', 'underline' => 'Underline']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $command => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <button type="button" data-writing-command="<?php echo e($command); ?>" aria-label="<?php echo e($label); ?>" title="<?php echo e($label); ?>" aria-pressed="false" disabled><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
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
            <div class="proposal-writing-action-group" role="group" aria-label="History">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['undo' => 'Undo', 'redo' => 'Redo']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $command => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <button type="button" data-writing-command="<?php echo e($command); ?>" aria-label="<?php echo e($label); ?>" title="<?php echo e($label); ?>" disabled><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
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
            <div class="proposal-writing-action-group" role="group" aria-label="Additional tools">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['lists' => 'Lists', 'insert' => 'Insert']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $menu => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <button type="button" data-writing-menu-toggle="<?php echo e($menu); ?>" aria-haspopup="menu" aria-expanded="false" aria-controls="proposal-writing-<?php echo e($menu); ?>-menu" disabled><span><?php echo e($label); ?></span><svg class="proposal-writing-menu-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" aria-hidden="true"><path d="m4 6 4 4 4-4" /></svg></button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
            <div class="proposal-writing-action-group" role="group" aria-label="Clear formatting">
                <button type="button" data-writing-command="removeFormat" aria-label="Clear formatting" title="Clear formatting" disabled><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7a9485575b48ddf8197622ac7859a0fe = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.proposal-writing-icon','data' => ['name' => 'removeFormat']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('proposal-writing-icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'removeFormat']); ?>
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
<?php endif; ?><span>Clear formatting</span></button>
            </div>
        </div>
        <div class="proposal-writing-buttons proposal-writing-primary-action" role="group" aria-label="Insert">
            <button type="button" data-writing-cite class="proposal-writing-cite" disabled title="Insert an IEEE citation and add its reference automatically"><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
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
<?php endif; ?><span>Insert citation</span></button>
        </div>
    </div>
    <div id="proposal-writing-lists-menu" data-writing-menu="lists" hidden class="proposal-writing-menu" role="menu" aria-label="Lists">
        <button type="button" role="menuitem" tabindex="-1" data-writing-command="insertUnorderedList" disabled><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
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
        <button type="button" role="menuitem" tabindex="-1" data-writing-command="insertOrderedList" disabled><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
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
    <div id="proposal-writing-insert-menu" data-writing-menu="insert" hidden class="proposal-writing-menu" role="menu" aria-label="Insert">
        <button type="button" role="menuitem" tabindex="-1" data-writing-image disabled title="Select a narrative section that supports figures"><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
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
        <button type="button" role="menuitem" tabindex="-1" data-writing-table aria-expanded="false" aria-controls="proposal-table-picker" disabled><?php if (isset($component)) { $__componentOriginal7a9485575b48ddf8197622ac7859a0fe = $component; } ?>
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
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal90e243942d22f6a4722cd614e5732fed)): ?>
<?php $attributes = $__attributesOriginal90e243942d22f6a4722cd614e5732fed; ?>
<?php unset($__attributesOriginal90e243942d22f6a4722cd614e5732fed); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal90e243942d22f6a4722cd614e5732fed)): ?>
<?php $component = $__componentOriginal90e243942d22f6a4722cd614e5732fed; ?>
<?php unset($__componentOriginal90e243942d22f6a4722cd614e5732fed); ?>
<?php endif; ?>
<?php /**PATH C:\laragon\www\athena-app\src\resources\views\components\detailed-proposal-writing-toolbar.blade.php ENDPATH**/ ?>