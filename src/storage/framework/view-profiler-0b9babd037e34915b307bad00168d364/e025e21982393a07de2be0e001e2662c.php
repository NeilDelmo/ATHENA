<?php $__env->startSection('page_title', 'Too many requests'); ?>
<?php $__env->startSection('code', '429'); ?>
<?php $__env->startSection('heading', 'Please slow down for a moment.'); ?>
<?php $__env->startSection('message'); ?>
    ATHENA received too many requests in a short time. Wait a little, then try again.
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\athena-app\src\resources\views\errors\429.blade.php ENDPATH**/ ?>