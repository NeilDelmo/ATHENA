<?php $__env->startSection('page_title', 'Session expired'); ?>
<?php $__env->startSection('code', '419'); ?>
<?php $__env->startSection('heading', 'Your session has expired.'); ?>
<?php $__env->startSection('message'); ?>
    Your session expired before this action could be completed. Refresh the page, then sign in again if prompted and retry your action.
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\athena-app\src\resources\views\errors\419.blade.php ENDPATH**/ ?>