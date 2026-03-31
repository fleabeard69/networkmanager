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

$count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($count === 0) {
    $pw = getenv('ADMIN_PASS');
    if ($pw === false || $pw === '') {
        fwrite(STDERR, "[bootstrap] Error: ADMIN_PASS is required for initial setup but is not set.\n");
        fwrite(STDERR, "[bootstrap] Set ADMIN_PASS in your .env file and restart the container.\n");
        exit(1);
    }
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (:u, :p)");
    $stmt->execute([':u' => $admin, ':p' => $hash]);
    echo "[bootstrap] Admin user '{$admin}' created.\n";
} else {
    echo "[bootstrap] Users exist, skipping admin creation.\n";
}
