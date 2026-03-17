<?php
/**
 * Application configuration.
 * Reads settings from environment variables with sensible defaults.
 * Must be included BEFORE session_start() on every page.
 */

// ── Database ──────────────────────────────────────────────────────────
define('DB_HOST',       getenv('DB_HOST')       ?: 'db-master');
define('DB_NAME',       getenv('DB_NAME')       ?: 'nsa');
define('DB_USER',       getenv('DB_USER')       ?: 'webapp');
define('DB_PASS',       getenv('DB_PASS')       ?: '');
define('DB_SLAVE_HOST', getenv('DB_SLAVE_HOST') ?: 'db-slave');

// ── Redis (session store) ─────────────────────────────────────────────
define('REDIS_HOST', getenv('REDIS_HOST') ?: 'redis');
define('REDIS_PORT', 6379);
define('REDIS_PASS', getenv('REDIS_PASS') ?: '');

// ── Mail ──────────────────────────────────────────────────────────────
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'mailhog');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 1025));
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'noreply@nsa.local');

// ── Application ───────────────────────────────────────────────────────
define('APP_URL',  rtrim(getenv('APP_URL') ?: 'https://nsa.local:8443', '/'));
define('APP_NAME', 'NSA Web App');
define('INSTANCE', getenv('INSTANCE_NAME') ?: gethostname());

// ── PHP session → Redis ───────────────────────────────────────────────
ini_set('session.save_handler', 'redis');
ini_set('session.save_path',
    sprintf('tcp://%s:%d?auth=%s', REDIS_HOST, REDIS_PORT, urlencode(REDIS_PASS)));
ini_set('session.gc_maxlifetime', 3600);
ini_set('session.cookie_secure',  1);
ini_set('session.cookie_httponly', 1);

// ── Database helper ───────────────────────────────────────────────────
function getDB(): PDO
{
    static $pdo = null;
    static $schemaChecked = false;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    if (!$schemaChecked) {
        ensureSchema($pdo);
        $schemaChecked = true;
    }

    return $pdo;
}

/**
 * Ensure required app tables exist.
 *
 * This makes the app resilient if DB init scripts were skipped, if a custom
 * database name is used, or after restoring an empty DB volume.
 */
function ensureSchema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username           VARCHAR(50)  NOT NULL,
            email              VARCHAR(100) NOT NULL,
            password_hash      VARCHAR(255) NOT NULL,
            confirmed          TINYINT(1)   NOT NULL DEFAULT 0,
            confirmation_token VARCHAR(64)  NULL,
            created_at         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_username (username),
            UNIQUE KEY uq_email    (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS items (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     INT UNSIGNED NOT NULL,
            title       VARCHAR(200) NOT NULL,
            description TEXT         NULL,
            created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_items_user
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

// ── Auth helpers ──────────────────────────────────────────────────────
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function requireGuest(): void
{
    if (isLoggedIn()) {
        header('Location: /dashboard.php');
        exit;
    }
}

// ── Minimal SMTP mailer (works with MailHog, no auth required) ────────
function sendEmail(string $to, string $subject, string $body): bool
{
    $socket = @fsockopen(MAIL_HOST, MAIL_PORT, $errno, $errstr, 10);
    if (!$socket) {
        error_log(sprintf('sendEmail: fsockopen(%s:%d) failed: [%d] %s', MAIL_HOST, MAIL_PORT, $errno, $errstr));
        return false;
    }

    $read = function () use ($socket): string {
        return fgets($socket, 1024) ?: '';
    };

    $read(); // server greeting

    fwrite($socket, "EHLO nsa.local\r\n");
    while ($line = $read()) {
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }

    fwrite($socket, 'MAIL FROM:<' . MAIL_FROM . ">\r\n");
    $read();
    fwrite($socket, "RCPT TO:<{$to}>\r\n");
    $read();
    fwrite($socket, "DATA\r\n");
    $read();

    $headers  = 'From: ' . APP_NAME . ' <' . MAIL_FROM . ">\r\n";
    $headers .= "To: {$to}\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "Content-Type: text/plain; charset=utf-8\r\n";

    fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
    $read();

    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return true;
}

// ── CSRF helpers ──────────────────────────────────────────────────────
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token.');
    }
}
