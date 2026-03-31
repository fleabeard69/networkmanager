<?php
declare(strict_types=1);

class Auth
{
    public function __construct(private Database $db) {}

    public function check(): bool
    {
        return (bool) Session::get('user_id');
    }

    /**
     * Abort with a 401/redirect if the session has no authenticated user.
     * Call from controller constructors to provide defense-in-depth
     * independent of the global gate in index.php.
     */
    public static function requireLogin(): void
    {
        if (!Session::get('user_id')) {
            if (str_starts_with($_SERVER['REQUEST_URI'] ?? '/', '/api/')) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Unauthenticated']);
                exit;
            }
            header('Location: /login');
            exit;
        }
    }

    public function attempt(string $username, string $password): bool
    {
        $user = $this->db->fetchOne(
            'SELECT id, password FROM users WHERE username = :u',
            [':u' => $username]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            // Constant-time comparison via verify prevents timing attacks;
            // also run a dummy verify when user not found to equalize timing.
            if (!$user) {
                password_verify($password, '$2y$12$invalidhashpadding000000000000000000000000000000000000000');
            }
            return false;
        }

        Session::regenerate();
        Session::set('user_id', $user['id']);
        Session::set('username', $username);
        return true;
    }

    public function logout(): void
    {
        Session::destroy();
    }

    public function username(): string
    {
        return (string) Session::get('username', '');
    }
}
