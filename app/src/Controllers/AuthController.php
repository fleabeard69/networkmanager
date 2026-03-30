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

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            Session::flash('error', 'Username and password are required.');
            header('Location: /login');
            exit;
        }

        if ($this->auth->attempt($username, $password)) {
            header('Location: /');
            exit;
        }

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
