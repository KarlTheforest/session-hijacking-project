<?php
/**
 * SECURE GUESTBOOK APPLICATION
 * ----------------------------
 * Same feature set as the vulnerable app, but with all 5 defenses:
 *   1. HttpOnly + Secure + SameSite cookies   (SecureSession)
 *   2. Content-Security-Policy header
 *   3. Input validation + output encoding
 *   4. WAF-style request inspection (see waf_filter())
 *   5. Advanced session management            (SecureSession)
 *
 * For a local (HTTP) lab demo, pass false to disable the Secure flag
 * so the cookie is still set without HTTPS. Use true in production.
 */

require_once __DIR__ . '/SecureSession.php';

// Defense #1 & #5: hardened session (set true when serving over HTTPS)
SecureSession::start(false);

// Simulate an authenticated user for the demo
if (!SecureSession::isAuthenticated()) {
    SecureSession::login(12345, 'demo_user', 'member');
}

// Defense #2: Content Security Policy + related security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self'; "
     . "object-src 'none'; base-uri 'self'; form-action 'self';");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

require_once __DIR__ . '/security.php'; // sanitize() + waf_filter()

$error = null;
$commentsFile = __DIR__ . '/comments.txt';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $raw = $_POST['comment'];

    // Defense #4: WAF-style inspection BEFORE processing
    if (waf_filter($raw)) {
        http_response_code(403);
        $error = 'Request blocked by WAF: malicious pattern detected.';
    } else {
        // Defense #3: validate length, then sanitize for storage
        if (mb_strlen($raw) > 500) {
            $error = 'Comment too long (max 500 characters).';
        } elseif (trim($raw) === '') {
            $error = 'Comment cannot be empty.';
        } else {
            $clean = sanitize($raw);
            file_put_contents($commentsFile, $clean . "\n", FILE_APPEND | LOCK_EX);
        }
    }
}

$stored = is_file($commentsFile)
    ? file($commentsFile, FILE_IGNORE_NEW_LINES)
    : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Guestbook</title>
    <style>
        body{font-family:Arial,sans-serif;max-width:760px;margin:40px auto;padding:0 16px}
        .banner{background:#e3f9e5;border:1px solid #2e7d32;color:#1b5e20;padding:10px;border-radius:6px}
        .err{background:#fdecea;border:1px solid #c62828;color:#b71c1c;padding:10px;border-radius:6px}
        .comment{background:#f3f3f3;padding:12px;margin:10px 0;border-radius:6px}
        textarea{width:100%;height:90px;padding:8px}
        button{background:#2e7d32;color:#fff;border:0;padding:10px 18px;border-radius:5px;cursor:pointer}
    </style>
</head>
<body>
    <div class="banner">🔒 Protected with 5-layer defense-in-depth.</div>
    <h1>Secure Guestbook</h1>
    <p>Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></strong></p>

    <?php if ($error): ?>
        <p class="err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <h2>Comments</h2>
    <?php if (empty($stored)): ?>
        <p><em>No comments yet.</em></p>
    <?php else: ?>
        <?php foreach ($stored as $c): ?>
            <!-- Stored value is already encoded; safe to echo -->
            <div class="comment"><?php echo $c; ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h2>Leave a comment</h2>
    <form method="POST">
        <textarea name="comment" maxlength="500" placeholder="Try the same XSS payload here — it will be neutralised"></textarea>
        <br><br>
        <button type="submit">Submit</button>
    </form>
</body>
</html>
