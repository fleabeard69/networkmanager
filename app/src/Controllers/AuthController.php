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

        // Rate limiting: max 5 failed attempts per 5 minutes
        $attempts  = Session::get('login_attempts', 0);
        $attemptAt = Session::get('login_attempts_at', 0);
        if (time() - $attemptAt > 300) {
            $attempts = 0;
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
            Session::forget('login_attempts');
            Session::forget('login_attempts_at');
            header('Location: /');
            exit;
        }

        Session::set('login_attempts', $attempts + 1);
        Session::set('login_attempts_at', $attemptAt === 0 ? time() : $attemptAt);
        Session::flash('error', 'Invalid username or password.');
        header('Location: /login');
        exit;
    }

    public function logout(): void
    {
        $this->auth->logout();
        header('Location: /login');
        exit;
    }
}
