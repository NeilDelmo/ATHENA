<?php $__env->startSection('page_title', 'Access denied'); ?>
<?php $__env->startSection('code', '403'); ?>
<?php $__env->startSection('heading', "This page isn't for your account."); ?>
<?php $__env->startSection('message'); ?>
    You're signed in, but your current role does not have access to this page. Nothing is wrong with your account.
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\athena-app\src\resources\views/errors/403.blade.php ENDPATH**/ ?>