<?php
declare(strict_types=1);

/**
 * Runs once at container startup.
 * Creates the admin user from env vars if no users exist yet.
 */

$host  = getenv('DB_HOST') ?: 'db';
$name  = getenv('DB_NAME');
$user  = getenv('DB_USER');
$pass  = getenv('DB_PASS');
$admin = getenv('ADMIN_USER') ?: 'admin';

$dsn = "pgsql:host={$host};dbname={$name}";

$maxAttempts = 10;
$pdo = null;
for ($i = 1; $i <= $maxAttempts; $i++) {
    try {
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        break;
    } catch (PDOException $e) {
        if ($i === $maxAttempts) {
            fwrite(STDERR, "[bootstrap] Could not connect to database: {$e->getMessage()}\n");
            exit(1);
        }
        fwrite(STDOUT, "[bootstrap] Waiting for database (attempt {$i}/{$maxAttempts})...\n");
        sleep(2);
    }
}

// ── APP_SECRET validation ─────────────────────────────────────────────────────
$appSecret = getenv('APP_SECRET');
if ($appSecret === false || $appSecret === '' || $appSecret === 'change-this-to-a-long-random-string') {
    fwrite(STDERR, "[bootstrap] Warning: APP_SECRET is not set or uses the default example value.\n");
    fwrite(STDERR, "[bootstrap] Set a unique random APP_SECRET in your .env file.\n");
    fwrite(STDERR, "[bootstrap] Generate one with: openssl rand -hex 32\n");
}
unset($appSecret);

$count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($count === 0) {
    $pw = getenv('ADMIN_PASS');
    if ($pw === false || $pw === '') {
        fwrite(STDERR, "[bootstrap] Error: ADMIN_PASS is required for initial setup but is not set.\n");
        fwrite(STDERR, "[bootstrap] Set ADMIN_PASS in your .env file and restart the container.\n");
        exit(1);
    }
    if ($pw === 'changeme_strong_password') {
        fwrite(STDERR, "[bootstrap] Warning: ADMIN_PASS uses the default example value.\n");
        fwrite(STDERR, "[bootstrap] Change it in your .env file before exposing this service.\n");
    }
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (:u, :p)");
    $stmt->execute([':u' => $admin, ':p' => $hash]);
    echo "[bootstrap] Admin user '{$admin}' created.\n";
} else {
    echo "[bootstrap] Users exist, skipping admin creation.\n";
}
