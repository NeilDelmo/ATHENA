#!/bin/bash
set -euo pipefail
run=/var/tmp/athena-capacity-20261103
root=$run/rootfs
test -f "$run/prepare-complete"
cp /mnt/c/laragon/www/athena-app/src/tmp/capacity-full-20261103/{supervisord.conf,php-fpm.conf,task.php,wait-db.sh,workload-documents.php,workload-extraction.php,workload-other.php} "$root/app/benchmark/"
chmod 755 "$root/run/mysqld"
mount -t proc proc "$root/proc"
mount --rbind /dev "$root/dev"
mount --make-rslave "$root/dev"
ip link set lo up
exec chroot "$root" /usr/bin/supervisord -c /app/benchmark/supervisord.conf
