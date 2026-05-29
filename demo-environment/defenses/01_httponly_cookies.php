<?php
/**
 * DEFENSE #1 — HttpOnly, Secure & SameSite Cookies
 * ================================================
 * Goal: stop JavaScript (and therefore XSS) from reading the
 *       session cookie, and stop it travelling over plain HTTP.
 *
 * Effectiveness: ~98%   Performance: none   Complexity: very low
 */

// ---------- BEFORE (vulnerable) ----------
// session_start();                 // default cookie is readable by JS
// document.cookie -> "PHPSESSID=abc123" (attacker captures this)

// ---------- AFTER (secure) ----------
// Option A: configure via session_set_cookie_params (PHP 7.3+)
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',          // current host
    'secure'   => true,        // only sent over HTTPS
    'httponly' => true,        // JavaScript CANNOT read it
    'samesite' => 'Strict',    // not sent on cross-site requests (anti-CSRF)
]);
session_start();

// Option B: configure individual cookies the same way
setcookie('auth_token', bin2hex(random_bytes(16)), [
    'expires'  => time() + 3600,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);

// Option C: global defaults in php.ini
//   session.cookie_httponly = 1
//   session.cookie_secure   = 1
//   session.cookie_samesite = Strict

/*
 VERIFY IT WORKS
 ---------------
 1. Load the page, open DevTools > Application > Cookies.
    The session cookie row shows HttpOnly = true, Secure = true.
 2. In the DevTools console run:  document.cookie
    The HttpOnly cookie is NOT listed -> XSS cannot steal it.
 3. Re-run the CookieCatcher attack: the captured value is empty.
*/
echo "HttpOnly/Secure/SameSite cookie configured. JS can no longer read the session cookie.\n";
