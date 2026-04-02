<?php
declare(strict_types=1);

class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $proto  = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        $secure = $proto === 'https' || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_name('netmgr_session');
        session_start();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    public static function getFlash(string $key): ?string
    {
        $msg = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $msg;
    }

    public static function flashInput(array $data): void
    {
        $_SESSION['_flash']['_input'] = $data;
    }

    public static function getFlashInput(): array
    {
        $data = $_SESSION['_flash']['_input'] ?? [];
        unset($_SESSION['_flash']['_input']);
        return $data;
    }
}
