<?php $__env->startSection('page_title', 'Access denied'); ?>
<?php $__env->startSection('code', '403'); ?>
<?php $__env->startSection('heading', "This page isn't for your account."); ?>
<?php $__env->startSection('message'); ?>
    Your account or current workspace does not have access to this page. Return to your dashboard to choose an available workspace. If you believe you should have access, contact the system administrator.
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\athena-app\src\resources\views\errors\403.blade.php ENDPATH**/ ?>