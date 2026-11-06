#!/bin/bash
set -euo pipefail

run=/var/tmp/athena-memory-20261104
input=/mnt/c/laragon/www/athena-app/src/tmp/memory-20261104
root=$run/rootfs
mkdir -p "$run/tools" "$root"
printf '%s\n' "[setup] disposable filesystem: $root"

if [ ! -x "$root/bin/bash" ]; then
    cd "$run/tools"
    apt-get download debootstrap
    for package in debootstrap_*.deb; do dpkg-deb -x "$package" unpacked; done
    export DEBOOTSTRAP_DIR="$run/tools/unpacked/usr/share/debootstrap"
    "$run/tools/unpacked/usr/sbin/debootstrap" --variant=minbase --components=main,universe \
        --keyring=/usr/share/keyrings/ubuntu-archive-keyring.gpg resolute "$root" http://archive.ubuntu.com/ubuntu
fi

mkdir -p "$root/proc" "$root/dev" "$root/sys" "$root/input" "$root/output" "$root/app"
mount -t proc proc "$root/proc"
mount --rbind /dev "$root/dev"
mount --make-rslave "$root/dev"
mount --bind "$input" "$root/input"
mount -o remount,bind,ro "$root/input"
mount --bind "$input" "$root/output"
printf '#!/bin/sh\nexit 101\n' > "$root/usr/sbin/policy-rc.d"
chmod 755 "$root/usr/sbin/policy-rc.d"
printf '%s\n' \
    'deb http://archive.ubuntu.com/ubuntu resolute main universe' \
    'deb http://archive.ubuntu.com/ubuntu resolute-updates main universe' \
    'deb http://security.ubuntu.com/ubuntu resolute-security main universe' > "$root/etc/apt/sources.list"
cp -L /etc/resolv.conf "$root/etc/resolv.conf"

chroot "$root" /usr/bin/env DEBIAN_FRONTEND=noninteractive /bin/bash -euc '
    apt-get update
    apt-get install -y --no-install-recommends \
        ca-certificates curl procps iproute2 unzip \
        php8.5-cli php8.5-fpm php8.5-mysql php8.5-mbstring php8.5-xml php8.5-curl \
        php8.5-zip php8.5-gd php8.5-bcmath php8.5-intl php8.5-sqlite3 \
        mysql-server nginx supervisor libreoffice-writer libreoffice-calc python3-uno \
        poppler-utils nodejs npm fonts-liberation fonts-crosextra-carlito fonts-crosextra-caladea \
        python3-reportlab python3-pil
    for attempt in $(seq 1 180); do
        if [ -f /input/snapshot-ready ]; then break; fi
        sleep 5
    done
    test -f /input/snapshot-ready
    cd /app
    tar -xf /input/source.tar
    tar -xf /input/vendor.tar
    mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    npm ci --ignore-scripts --omit=dev --no-audit --no-fund
    php -v
    node --version
    mysqld --version
    libreoffice --version
    pdftotext -v
    du -sh /app /var/lib/mysql
'
printf '%s\n' '[setup] completed'
touch "$run/setup-complete"
