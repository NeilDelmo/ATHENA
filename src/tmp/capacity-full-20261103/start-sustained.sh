#!/bin/bash
set -euo pipefail
root=/var/tmp/athena-capacity-20261103/rootfs
sed -i 's/^pm.max_children=2$/pm.max_children=4/' "$root/app/benchmark/php-fpm.conf"
grep -q '^pm.max_children=4$' "$root/app/benchmark/php-fpm.conf"
chroot "$root" /usr/bin/supervisorctl -c /app/benchmark/supervisord.conf restart fpm
exec /bin/bash /mnt/c/laragon/www/athena-app/src/tmp/capacity-full-20261103/run-sustained.sh
