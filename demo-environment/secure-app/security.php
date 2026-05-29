<?php
/**
 * security.php
 * ------------
 * Shared helpers for the secure app:
 *   - sanitize()   : Defense #3 (input validation / output encoding)
 *   - waf_filter() : Defense #4 (application-level WAF inspection)
 */

/**
 * Encode user input so it is rendered as text, never executed.
 * ENT_QUOTES encodes both single and double quotes.
 */
function sanitize(string $input): string
{
    $input = trim($input);
    $input = stripslashes($input);
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

/**
 * Lightweight WAF-style inspection. Returns TRUE if the input looks
 * malicious and should be blocked. In production prefer ModSecurity
 * (see ../defenses/04_waf_modsecurity.conf) — this is a teaching aid.
 */
function waf_filter(string $input): bool
{
    $patterns = [
        '/<\s*script/i',                 // <script>
        '/on\w+\s*=/i',                  // onerror=, onload=, onclick=...
        '/javascript\s*:/i',             // javascript: URIs
        '/<\s*(iframe|object|embed|svg)/i',
        '/document\s*\.\s*cookie/i',     // cookie theft attempt
        '/\b(eval|atob|btoa|fromCharCode)\s*\(/i',
    ];

    foreach ($patterns as $regex) {
        if (preg_match($regex, $input)) {
            error_log('WAF: blocked request matching ' . $regex);
            return true;
        }
    }
    return false;
}
