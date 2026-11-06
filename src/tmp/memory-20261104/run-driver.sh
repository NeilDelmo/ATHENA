#!/bin/bash
set -euo pipefail
unit=$1
name=$2
shift 2
pid=$(systemctl show "$unit" --property=MainPID --value)
cgroup=$(systemctl show "$unit" --property=ControlGroup --value)
test "$pid" -gt 1
exec nsenter -t "$pid" -n /usr/bin/python3 /mnt/c/laragon/www/athena-app/src/tmp/memory-20261104/driver.py "$cgroup" "$name" "$@"
