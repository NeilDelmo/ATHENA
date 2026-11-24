<?php $__env->startSection('page_title', 'Sign in required'); ?>
<?php $__env->startSection('code', '401'); ?>
<?php $__env->startSection('heading', 'Sign in to continue.'); ?>
<?php $__env->startSection('message'); ?>
    You need to sign in to open this page or complete this action.
<?php $__env->stopSection(); ?>
<?php $__env->startSection('recovery_url', route('login')); ?>
<?php $__env->startSection('recovery_label', 'Sign in'); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\athena-app\src\resources\views\errors\401.blade.php ENDPATH**/ ?>