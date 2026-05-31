<?php
/**
 * DEFENSE #5 — Advanced Session Management
 * ========================================
 * Goal: even if a cookie is somehow stolen, make it useless by binding
 *       the session to context and expiring it aggressively.
 *
 * Effectiveness: ~88%   Performance: +8ms   Complexity: high
 *
 * Techniques shown:
 *   - Regenerate session ID on login (anti-fixation)
 *   - Bind session to IP + User-Agent (anti-hijack)
 *   - Idle timeout
 *   - Absolute lifetime cap
 */

ini_set('session.use_strict_mode', '1');
session_start();

const IDLE_TIMEOUT     = 1800;   // 30 minutes of inactivity
const ABSOLUTE_TIMEOUT = 28800;  // 8 hours hard cap

/** Initialise binding data the first time we see the session. */
function init_session(): void {
    $_SESSION['ip']      = $_SERVER['REMOTE_ADDR']     ?? '';
    $_SESSION['ua']      = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['created'] = time();
    $_SESSION['last']    = time();
}

/** Validate the request against the bound session. */
function validate_session(): bool {
    // 1. IP must match
    if (($_SESSION['ip'] ?? '') !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
        return false; // likely a stolen cookie used elsewhere
    }
    // 2. User-Agent must match
    if (($_SESSION['ua'] ?? '') !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        return false;
    }
    // 3. Idle timeout
    if (time() - ($_SESSION['last'] ?? time()) > IDLE_TIMEOUT) {
        return false;
    }
    // 4. Absolute lifetime
    if (time() - ($_SESSION['created'] ?? time()) > ABSOLUTE_TIMEOUT) {
        return false;
    }
    return true;
}

/** Call right after verifying username/password. */
function secure_login(int $userId): void {
    session_regenerate_id(true);   // brand-new ID, invalidate the old one
    init_session();
    $_SESSION['user_id'] = $userId;
}

// ---------- Request lifecycle ----------
if (!isset($_SESSION['ip'])) {
    init_session();
}

if (!validate_session()) {
    // Destroy and force re-authentication
    $_SESSION = [];
    session_destroy();
    http_response_code(403);
    exit('Session invalid or expired — possible hijacking blocked.');
}

$_SESSION['last'] = time(); // refresh idle timer
echo "Session validated: IP + User-Agent bound, timeouts enforced.\n";

/*
 ATTACK SIMULATION
 -----------------
 Victim logs in from 10.0.2.15 (IE 11). Attacker steals the cookie
 and replays it from 10.0.2.3 (Firefox on Kali):
   - IP mismatch        -> validate_session() returns false
   - User-Agent mismatch-> validate_session() returns false
 Result: HTTP 403, session destroyed, hijack blocked.

 LIMITATION: mobile users / proxies whose IP changes legitimately may be
 logged out. Combine with re-auth prompts or device fingerprinting.
*/
