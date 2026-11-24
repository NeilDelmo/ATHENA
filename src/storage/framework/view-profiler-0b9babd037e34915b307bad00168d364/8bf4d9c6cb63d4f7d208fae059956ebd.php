<?php $__env->startSection('page_title', 'Action not allowed'); ?>
<?php $__env->startSection('code', '405'); ?>
<?php $__env->startSection('heading', 'This action is not available here.'); ?>
<?php $__env->startSection('message'); ?>
    Open the page again from your dashboard and use the controls provided there to complete your action.
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\athena-app\src\resources\views\errors\405.blade.php ENDPATH**/ ?>