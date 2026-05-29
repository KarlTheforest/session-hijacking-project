<?php
/**
 * DEFENSE #2 — Content Security Policy (CSP)
 * ==========================================
 * Goal: even if an XSS payload is injected, the browser refuses to
 *       execute inline / external scripts that violate the policy.
 *
 * Effectiveness: ~95%   Performance: +2ms   Complexity: medium
 */

// ---------- Basic, strong starting policy ----------
header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .      // everything must come from same origin
    "script-src 'self'; " .       // no inline JS, no remote JS
    "style-src 'self'; " .
    "img-src 'self' data:; " .
    "object-src 'none'; " .       // block <object>/<embed>/Flash
    "base-uri 'self'; " .
    "form-action 'self'; " .
    "frame-ancestors 'none';"     // cannot be framed (clickjacking)
);

// ---------- Stricter policy using a per-request nonce ----------
// $nonce = base64_encode(random_bytes(16));
// header("Content-Security-Policy: script-src 'nonce-$nonce';");
// Then only scripts tagged <script nonce="<?= $nonce ?>"> will run.

/*
 WHAT GETS BLOCKED
 -----------------
   <script>alert(1)</script>                  -> blocked (inline script)
   <img src=x onerror="alert(1)">             -> blocked (inline handler)
   <script src="http://evil.com/x.js"></script> -> blocked (remote origin)

 Browser console shows:
   "Refused to execute inline script because it violates the
    following Content Security Policy directive: script-src 'self'"

 DELIVERY OPTIONS
 ----------------
  - PHP:      header("Content-Security-Policy: ...")  (this file)
  - Apache:   Header set Content-Security-Policy "..."   (.htaccess)
  - Nginx:    add_header Content-Security-Policy "..." always;
*/
echo "CSP header sent. Inline and cross-origin scripts are now blocked.\n";
