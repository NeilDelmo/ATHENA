#!/bin/bash
set -euo pipefail
root=/var/tmp/athena-optimization-20261104/rootfs
optimization_pid=$(systemctl show athena-opt-runtime.service --property=MainPID --value)
test "$optimization_pid" -gt 1
exec nsenter -t "$optimization_pid" -m -n chroot "$root" /usr/bin/env \
    APP_ENV=testing DB_CONNECTION=mysql DB_SOCKET=/run/mysqld/mysqld.sock \
    DB_DATABASE=athena_optimization_testing DB_USERNAME=root DB_PASSWORD= \
    CACHE_STORE=array SESSION_DRIVER=array MAIL_MAILER=array QUEUE_CONNECTION=sync BROADCAST_CONNECTION=null \
    /bin/bash -c 'cd /app; exec "$@"' bash "$@"
