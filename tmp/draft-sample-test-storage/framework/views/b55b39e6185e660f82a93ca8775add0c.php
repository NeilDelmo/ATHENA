<?php $__env->startSection('page_title', 'Page not found'); ?>
<?php $__env->startSection('code', '404'); ?>
<?php $__env->startSection('heading', 'We could not find that page.'); ?>
<?php $__env->startSection('message'); ?>
    The link may be outdated, or the page may have been moved. Check the address or return to your dashboard.
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\athena-app\src\resources\views/errors/404.blade.php ENDPATH**/ ?>