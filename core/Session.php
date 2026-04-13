<?php
/**
 * O-CMS — Session Management
 *
 * Provides a static wrapper around PHP sessions with secure defaults
 * (HttpOnly, SameSite, Secure cookies) and helper methods for get/set/destroy.
 *
 * @package O-CMS
 * @version 1.0.0
 */
class Session {
    /**
     * Start the session with secure cookie settings if not already started.
     *
     * @return void
     */
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('ocms_session');
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => $isSecure,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }
    }

    /**
     * Retrieve a value from the session.
     *
     * @param string $key     The session key
     * @param mixed  $default Fallback value if the key does not exist
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Store a value in the session.
     *
     * @param string $key   The session key
     * @param mixed  $value The value to store
     * @return void
     */
    public static function set(string $key, $value): void {
        $_SESSION[$key] = $value;
    }

    /**
     * Remove a value from the session.
     *
     * @param string $key The session key to remove
     * @return void
     */
    public static function remove(string $key): void {
        unset($_SESSION[$key]);
    }

    /**
     * Destroy the session and invalidate the session cookie.
     *
     * @return void
     */
    public static function destroy(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Regenerate the session ID (should be called after login to prevent fixation).
     *
     * @return void
     */
    public static function regenerate(): void {
        session_regenerate_id(true);
    }
}
