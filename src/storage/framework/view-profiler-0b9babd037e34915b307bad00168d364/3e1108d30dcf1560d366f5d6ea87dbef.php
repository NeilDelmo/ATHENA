<?php $__env->startSection('page_title', 'Server error'); ?>
<?php $__env->startSection('code', '500'); ?>
<?php $__env->startSection('heading', 'Something went wrong on our side.'); ?>
<?php $__env->startSection('message'); ?>
    Your request could not be completed right now. Please try again shortly. If you were saving changes, check whether they were saved before trying again.
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\athena-app\src\resources\views\errors\500.blade.php ENDPATH**/ ?>