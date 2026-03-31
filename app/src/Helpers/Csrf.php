<?php
declare(strict_types=1);

class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            // HMAC folds APP_SECRET into the token so rotating the secret
            // immediately invalidates all outstanding tokens across all sessions.
            // random_bytes(32) provides the entropy regardless of secret strength.
            $secret = (string)(getenv('APP_SECRET') ?: '');
            $_SESSION[self::SESSION_KEY] = hash_hmac('sha256', bin2hex(random_bytes(32)), $secret);
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function verify(?string $token): bool
    {
        if ($token === null || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }
        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    /**
     * Renders a hidden CSRF input field for use in forms.
     * The token value is hex-encoded and safe to embed directly.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }
}
