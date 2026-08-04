<?php
declare(strict_types=1);

/**
 * FloFi — config.php
 * Central app configuration and shared helpers.
 * All secrets come from environment variables (set in IIS application settings
 * or a .env file loaded before this; never hardcode credentials here).
 */

/* ---- App ---- */
if (!defined('APP_NAME')) {
    define('APP_NAME', 'FloFi');
}
date_default_timezone_set('America/New_York');

/* ---- Database ---- */
if (!defined('DB_DSN')) {
    $dsn = getenv('DB_DSN');
    $dbUser = getenv('DB_USER');
    $dbPass = getenv('DB_PASS');
    if (!$dsn || !$dbUser || !$dbPass) {
        error_log('Missing DB environment variables (DB_DSN, DB_USER, DB_PASS).');
    }
    define('DB_DSN',  $dsn  ?: '');
    define('DB_USER', $dbUser ?: '');
    define('DB_PASS', $dbPass ?: '');
}

/* ---- Mail ---- */
if (!defined('MAIL_FROM')) {
    define('MAIL_FROM',   getenv('MAIL_FROM')   ?: '');
    define('MAIL_USER',   getenv('MAIL_USER')   ?: '');
    define('MAIL_PASS',   getenv('MAIL_PASS')   ?: '');
    define('MAIL_HOST',   getenv('MAIL_HOST')   ?: '');
    define('MAIL_PORT',   (int)(getenv('MAIL_PORT') ?: 465));
    define('MAIL_SECURE', getenv('MAIL_SECURE') ?: 'ssl');
}

if (!defined('MAIL_SMTP_OPTIONS')) {
    define('MAIL_SMTP_OPTIONS', serialize([
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ]));
}

/* ---- Composer Autoload ---- */
require_once __DIR__ . '/vendor/autoload.php';

/* ---- Countries & Phone Codes ---- */
use libphonenumber\PhoneNumberUtil;

$phoneUtil = PhoneNumberUtil::getInstance();
$COUNTRIES = [];

foreach ($phoneUtil->getSupportedRegions() as $region) {
    $countryCode = $phoneUtil->getCountryCodeForRegion($region);
    $flag = mb_convert_encoding('&#' . (127397 + ord($region[0])) . ';', 'UTF-8', 'HTML-ENTITIES')
          . mb_convert_encoding('&#' . (127397 + ord($region[1])) . ';', 'UTF-8', 'HTML-ENTITIES');
    $COUNTRIES[$region] = $flag . " " . \Locale::getDisplayRegion('-' . $region, 'en') . " (+" . $countryCode . ")";
}

/* ---- Theming ---- */
if (!function_exists('theme_colors')) {
    function theme_colors(string $theme): array {
        return match ($theme) {
            'red'    => ['#c62828', '#8e0000'],
            'blue'   => ['#1e88e5', '#1565c0'],
            'green'  => ['#2e7d32', '#1b5e20'],
            'purple' => ['#7e57c2', '#5e35b1'],
            'gray'   => ['#757575', '#424242'],
            'black'  => ['#111111', '#000000'],
            default  => ['#cc5500', '#9e3f00'], // orange
        };
    }
}

/* ---- Helpers ---- */
if (!function_exists('h')) {
    function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('dollars')) {
    function dollars(int $cents): string {
        return '$' . number_format($cents / 100, 2);
    }
}

/* ---- Session ---- */
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        @ini_set('session.cookie_secure', '1');
    }
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_strict_mode', '1');
    session_start();
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
    }
}
if (!function_exists('must_post_csrf')) {
    function must_post_csrf(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
            if (!$ok) { http_response_code(400); exit('Bad Request (CSRF).'); }
        }
    }
}

/* ---- DB Connection ---- */
if (!function_exists('db')) {
    function db(): PDO {
        static $pdo = null;
        if ($pdo === null) {
            $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        }
        return $pdo;
    }
}

/* ---- User Helpers ---- */
if (!function_exists('current_user')) {
    function current_user(): ?array {
        if (isset($_SESSION['uid'])) {
            $st = db()->prepare(
                "SELECT id, username, full_name, email, phone, country,
                        is_admin, is_disabled, theme, must_reset_password
                 FROM accounts WHERE id=:id"
            );
            $st->execute([':id' => $_SESSION['uid']]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
            if (!$u || $u['is_disabled']) return null;
            return $u;
        }

        if (!empty($_COOKIE['remember_me'])) {
            $token = $_COOKIE['remember_me'];
            $st = db()->prepare(
                "SELECT r.account_id, a.id, a.username, a.full_name, a.email,
                        a.phone, a.country, a.is_admin, a.is_disabled,
                        a.theme, a.must_reset_password, r.expires_at
                 FROM remember_tokens r
                 JOIN accounts a ON a.id = r.account_id
                 WHERE r.token = :t
                 LIMIT 1"
            );
            $st->execute([':t' => $token]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
            if ($u && !$u['is_disabled']) {
                if (strtotime($u['expires_at']) > time()) {
                    $_SESSION['uid'] = (int)$u['id'];
                    return $u;
                }
                $del = db()->prepare("DELETE FROM remember_tokens WHERE token=:t");
                $del->execute([':t' => $token]);
                setcookie('remember_me', '', time() - 3600, '/');
            }
        }

        return null;
    }
}
if (!function_exists('require_login')) {
    function require_login(): array {
        $u = current_user();
        if (!$u) { header('Location: index.php'); exit; }
        return $u;
    }
}
if (!function_exists('require_admin')) {
    function require_admin(): array {
        $u = require_login();
        if (empty($u['is_admin'])) { header('Location: index.php?app=1'); exit; }
        return $u;
    }
}

/* ---- Admin Log ---- */
if (!function_exists('log_admin_action')) {
    function log_admin_action(PDO $db, array $admin, string $action, ?int $targetUser = null, ?string $details = null): void {
        try {
            $st = $db->prepare(
                "INSERT INTO admin_log (admin_id, admin_name, action, target_user, details)
                 VALUES (:aid, :an, :a, :t, :d)"
            );
            $st->execute([
                ':aid' => $admin['id'] ?? null,
                ':an'  => $admin['full_name'] ?? 'System',
                ':a'   => $action,
                ':t'   => $targetUser,
                ':d'   => $details,
            ]);
        } catch (Throwable $e) {
            error_log('Admin log insert failed: ' . $e->getMessage());
        }
    }
}
