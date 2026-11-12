<?php
/**
 * O-CMS — Gestione Sessioni
 */
class Session {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            // Un cookie per installazione, valido solo nel suo percorso: prima /s/ e
            // /o-cms-ng/ sullo stesso dominio condividevano la sessione e chi era loggato
            // nella sandbox risultava loggato anche sul sito vero.
            session_name('ocms_' . substr(sha1(self::installId()), 0, 10));
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $path = rtrim(function_exists('ocms_base_url') ? ocms_base_url() : '', '/') . '/';
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => $isSecure,
                'cookie_samesite' => 'Lax',
                'cookie_path' => $path,
                'use_strict_mode' => true,
            ]);
            // la sessione appartiene a questa installazione: dati scritti da altre app
            // dello stesso dominio non valgono come login
            if (($_SESSION['_ocms_install'] ?? null) !== self::installId()) {
                $_SESSION = ['_ocms_install' => self::installId()];
            }
        }
    }

    /**
     * Identificativo dell'installazione (percorso della cartella del CMS).
     */
    public static function installId(): string {
        return dirname(__DIR__);
    }

    public static function get(string $key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void {
        $_SESSION[$key] = $value;
    }

    public static function remove(string $key): void {
        unset($_SESSION[$key]);
    }

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
     * Rigenera l'ID sessione (da usare dopo login)
     */
    public static function regenerate(): void {
        session_regenerate_id(true);
    }
}
