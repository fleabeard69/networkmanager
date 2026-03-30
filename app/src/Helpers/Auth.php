<?php
declare(strict_types=1);

class Auth
{
    public function __construct(private Database $db) {}

    public function check(): bool
    {
        return (bool) Session::get('user_id');
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
