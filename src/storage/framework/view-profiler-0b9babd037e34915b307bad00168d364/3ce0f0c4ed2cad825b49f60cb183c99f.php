<?php $__env->startSection('page_title', 'Request could not be completed'); ?>
<?php $__env->startSection('code', $exception->getStatusCode()); ?>
<?php $__env->startSection('heading', 'Your request could not be completed.'); ?>
<?php $__env->startSection('message'); ?>
    ATHENA could not process this request. Return to your dashboard and try again using the controls on the page.
<?php $__env->stopSection(); ?>

<?php echo $__env->make('errors.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\athena-app\src\resources\views\errors\4xx.blade.php ENDPATH**/ ?>