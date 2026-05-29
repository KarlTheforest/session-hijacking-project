<?php
/**
 * VULNERABLE GUESTBOOK APPLICATION
 * ---------------------------------
 * WARNING: This application is INTENTIONALLY INSECURE.
 * It is for educational use in an ISOLATED lab environment ONLY.
 * DO NOT deploy this on a public server or real network.
 *
 * Vulnerabilities demonstrated:
 *  1. Reflected/Stored XSS (no input sanitization)
 *  2. Session cookie WITHOUT HttpOnly / Secure / SameSite flags
 *  3. No Content-Security-Policy header
 *  4. No session validation (IP / User-Agent / timeout)
 */

// Insecure session start (default PHP cookie params = JS-readable cookie)
session_start();

// Simulate a logged-in user so we have a session worth stealing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id']  = 12345;
    $_SESSION['username'] = 'demo_victim';
    $_SESSION['role']     = 'member';
}

// In-memory comment store for the demo (resets on server restart)
if (!isset($GLOBALS['comments'])) {
    $GLOBALS['comments'] = [];
}

// VULNERABLE: store raw, unsanitised input
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    // No htmlspecialchars(), no validation -> XSS is possible
    file_put_contents(__DIR__ . '/comments.txt', $_POST['comment'] . "\n", FILE_APPEND);
}

$stored = is_file(__DIR__ . '/comments.txt')
    ? file(__DIR__ . '/comments.txt', FILE_IGNORE_NEW_LINES)
    : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vulnerable Guestbook (LAB ONLY)</title>
    <style>
        body{font-family:Arial,sans-serif;max-width:760px;margin:40px auto;padding:0 16px}
        .banner{background:#ffe0e0;border:1px solid #d33;color:#900;padding:10px;border-radius:6px}
        .comment{background:#f3f3f3;padding:12px;margin:10px 0;border-radius:6px}
        textarea{width:100%;height:90px;padding:8px}
        button{background:#c0392b;color:#fff;border:0;padding:10px 18px;border-radius:5px;cursor:pointer}
    </style>
</head>
<body>
    <div class="banner">⚠️ INTENTIONALLY VULNERABLE — isolated lab use only.</div>
    <h1>Guestbook</h1>
    <p>Logged in as: <strong><?php echo $_SESSION['username']; ?></strong>
       (session id: <code><?php echo session_id(); ?></code>)</p>

    <h2>Comments</h2>
    <?php if (empty($stored)): ?>
        <p><em>No comments yet.</em></p>
    <?php else: ?>
        <?php foreach ($stored as $c): ?>
            <!-- VULNERABLE: raw output, no encoding -->
            <div class="comment"><?php echo $c; ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h2>Leave a comment</h2>
    <form method="POST">
        <textarea name="comment" placeholder="Type a comment (try an XSS payload in the lab)"></textarea>
        <br><br>
        <button type="submit">Submit</button>
    </form>
</body>
</html>
