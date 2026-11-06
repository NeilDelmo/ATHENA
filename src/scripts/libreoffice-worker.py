"""Keep the isolated POSIX office process group owned until its control pipe closes."""

import os
import select
import signal
import subprocess
import sys
import threading
import time


def run_once(command, timeout):
    launcher = None
    shutdown = None
    lock = threading.RLock()

    def kill_group(pid):
        try:
            os.killpg(pid, signal.SIGKILL)
        except ProcessLookupError:
            pass

    def stop_once(signum=None, frame=None, exit_code=None):
        nonlocal shutdown
        shutdown = exit_code or (128 + signum if signum else 1)
        with lock:
            if launcher is not None:
                kill_group(launcher.pid)
                os._exit(shutdown)

    def watch_owner():
        deadline = time.monotonic() + timeout if timeout > 0 else None
        while True:
            remaining = max(0, deadline - time.monotonic()) if deadline is not None else None
            if not select.select([0], [], [], remaining)[0]:
                stop_once(exit_code=124)
                return
            if not os.read(0, 4096):
                stop_once()
                return

    signal.signal(signal.SIGTERM, stop_once)
    signal.signal(signal.SIGINT, stop_once)
    threading.Thread(target=watch_owner, daemon=True).start()
    launcher = subprocess.Popen(command, stdin=subprocess.DEVNULL, start_new_session=True)
    if shutdown is not None:
        stop_once(exit_code=shutdown)
    # Keep the group leader's PID reserved until its descendants have been killed.
    os.waitid(os.P_PID, launcher.pid, os.WEXITED | os.WNOWAIT)
    with lock:
        kill_group(launcher.pid)
        finished = launcher
        launcher = None
        result = finished.wait()
    return result if result >= 0 else 128 - result


if len(sys.argv) > 1 and sys.argv[1] == '--once':
    sys.exit(run_once(sys.argv[3:], float(sys.argv[2])))


def stop(signum=None, frame=None):
    os.killpg(os.getpgrp(), signal.SIGKILL)


os.setsid()
signal.signal(signal.SIGTERM, stop)
signal.signal(signal.SIGINT, stop)
launcher = subprocess.Popen(sys.argv[1:], stdin=subprocess.DEVNULL)
try:
    sys.stdin.buffer.read()
finally:
    stop()
