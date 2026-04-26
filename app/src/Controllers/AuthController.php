<?php
declare(strict_types=1);

class AuthController
{
    public function __construct(private Auth $auth) {}

    public function showLogin(): void
    {
        if ($this->auth->check()) {
            header('Location: /');
            exit;
        }
        $error = Session::getFlash('error');
        render('login', ['error' => $error]);
    }

    public function login(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }

        $ip = $this->getClientIp();

        // IP-based rate limit: stored server-side, not bypassable by discarding the session cookie
        if ($this->auth->isRateLimited($ip)) {
            Session::flash('error', 'Too many failed login attempts. Please wait a few minutes.');
            header('Location: /login');
            exit;
        }

        // Session-based rate limit: secondary guard for normal browser flows
        $attempts  = Session::get('login_attempts', 0);
        $attemptAt = Session::get('login_attempts_at', 0);
        if (time() - $attemptAt > 300) {
            $attempts  = 0;
            $attemptAt = 0;
        }
        if ($attempts >= 5) {
            Session::flash('error', 'Too many failed login attempts. Please wait a few minutes.');
            header('Location: /login');
            exit;
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            Session::flash('error', 'Username and password are required.');
            header('Location: /login');
            exit;
        }

        if ($this->auth->attempt($username, $password)) {
            $this->auth->clearFailedAttempts($ip);
            Session::forget('login_attempts');
            Session::forget('login_attempts_at');
            header('Location: /');
            exit;
        }

        $this->auth->recordFailedAttempt($ip);
        Session::set('login_attempts', $attempts + 1);
        Session::set('login_attempts_at', $attemptAt === 0 ? time() : $attemptAt);
        Session::flash('error', 'Invalid username or password.');
        header('Location: /login');
        exit;
    }

    /**
     * Returns the client IP for rate limiting.
     * nginx resolves $remote_addr via the real_ip_module (trusting X-Real-IP only
     * from SWAG's known static IP), so REMOTE_ADDR is always the correct value —
     * real client IP via SWAG, actual connecting IP for direct localhost access.
     * Returns '0.0.0.0' if the value is not a valid IP.
     */
    private function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ip = trim($ip);
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function logout(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            header('Location: /');
            exit;
        }
        $this->auth->logout();
        header('Location: /login');
        exit;
    }
}
