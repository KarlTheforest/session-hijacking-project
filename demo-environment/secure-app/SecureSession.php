<?php
/**
 * SecureSession
 * -------------
 * Hardened session manager combining several defenses:
 *  - HttpOnly + Secure + SameSite cookie flags
 *  - Strict mode (reject uninitialised session IDs)
 *  - Session ID regeneration on login (anti-fixation)
 *  - IP address + User-Agent binding (anti-hijack)
 *  - Idle timeout
 */
class SecureSession
{
    /** Idle timeout in seconds (30 minutes). */
    private static $timeout = 1800;

    /** Start a hardened session and validate it. */
    public static function start(bool $secureCookie = true): void
    {
        // Harden cookie + session engine BEFORE session_start()
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,          // JavaScript cannot read the cookie
            'secure'   => $secureCookie, // only sent over HTTPS
            'samesite' => 'Strict',      // mitigates CSRF
        ]);

        session_start();

        if (!isset($_SESSION['_initiated'])) {
            self::initFingerprint();
        }

        if (!self::isValid()) {
            self::destroy();
            http_response_code(403);
            exit('Session validation failed: possible hijacking detected.');
        }

        if (self::isExpired()) {
            self::destroy();
            http_response_code(440);
            exit('Session expired. Please log in again.');
        }

        $_SESSION['_last_activity'] = time();
    }

    /** Record the fingerprint when the session is first created. */
    private static function initFingerprint(): void
    {
        $_SESSION['_initiated']     = true;
        $_SESSION['_ip']            = $_SERVER['REMOTE_ADDR']     ?? '';
        $_SESSION['_ua']            = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['_created']       = time();
        $_SESSION['_last_activity'] = time();
    }

    /** Validate IP + User-Agent against the stored fingerprint. */
    private static function isValid(): bool
    {
        if (($_SESSION['_ip'] ?? null) !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
            error_log('SecureSession: IP mismatch (possible hijack)');
            return false;
        }
        if (($_SESSION['_ua'] ?? null) !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            error_log('SecureSession: User-Agent mismatch (possible hijack)');
            return false;
        }
        return true;
    }

    /** Check idle timeout. */
    private static function isExpired(): bool
    {
        if (!isset($_SESSION['_last_activity'])) {
            return false;
        }
        return (time() - $_SESSION['_last_activity']) > self::$timeout;
    }

    /** Call after a successful authentication to prevent fixation. */
    public static function login(int $userId, string $username, string $role = 'member'): void
    {
        session_regenerate_id(true); // new ID, delete old
        $_SESSION['user_id']  = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['role']     = $role;
        $_SESSION['_created'] = time();
    }

    /** Fully destroy the session and clear the cookie. */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function isAuthenticated(): bool
    {
        return !empty($_SESSION['user_id']);
    }
}
