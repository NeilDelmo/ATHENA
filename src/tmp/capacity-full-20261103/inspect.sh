#!/bin/sh
set -u
echo '=== OS ==='
cat /etc/os-release
uname -r
echo '=== Memory ==='
free -m
echo '=== Tools ==='
command -v php php-fpm8.4 php-fpm8.3 mysqld mariadbd node libreoffice soffice pdftotext pdftoppm pdfinfo docker systemd-run unshare debootstrap apt-get dpkg-deb curl wget || true
echo '=== Installed binaries ==='
ls -l /usr/bin/php* /usr/sbin/php-fpm* /usr/sbin/mysqld /usr/bin/node /usr/bin/unshare /usr/bin/systemd-run /usr/bin/debootstrap 2>/dev/null || true
echo '=== Cgroups ==='
cat /proc/1/comm
cat /sys/fs/cgroup/cgroup.controllers
findmnt -n -o OPTIONS /sys/fs/cgroup
echo '=== Package candidates ==='
apt-cache policy php-cli mysql-server mariadb-server libreoffice-writer poppler-utils debootstrap docker.io 2>/dev/null | head -100
echo '=== Storage ==='
df -h / /tmp
