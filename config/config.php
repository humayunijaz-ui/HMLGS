<?php
/**
 * Application configuration and session bootstrap
 */

// Show errors in development. Set to 0 in production.
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_NAME', 'Hostel Merit List Generator System (HMLGS)');
define('BASE_URL', '/HMLGS/');
define('SESSION_TIMEOUT', 1800); // 30 minutes idle timeout

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Session idle timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['flash_error'] = 'Your session expired. Please log in again.';
}
$_SESSION['last_activity'] = time();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ------------------------------------------------------------
// Email / SMTP settings — fill these in to enable outgoing email
// (merit-list notifications, selection confirmations, etc.)
// Leave SMTP_HOST empty to disable email sending (pages will
// still work, emails are just skipped and logged).
// ------------------------------------------------------------
define('SMTP_HOST', '');              // e.g. 'smtp.gmail.com'
define('SMTP_PORT', 587);             // 587 = STARTTLS, 465 = SSL
define('SMTP_ENCRYPTION', 'tls');     // 'tls', 'ssl', or ''
define('SMTP_USERNAME', '');          // full email address
define('SMTP_PASSWORD', '');          // app password (not your normal password, for Gmail)
define('SMTP_FROM_EMAIL', 'noreply@hmlgs.local');
define('SMTP_FROM_NAME', 'HMLGS - Hostel Merit List System');

// Base URL students/admin use to reach this system (used in emails)
define('SITE_URL', 'http://localhost' . BASE_URL);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';
