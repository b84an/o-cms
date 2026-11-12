<?php
/**
 * O-CMS — Sistema Hook/Eventi
 *
 * Permette ai plugin di agganciarsi a eventi del CMS.
 */
class Hooks {
    private static array $listeners = [];

    /**
     * Registra un listener per un evento
     */
    public static function on(string $event, callable $callback, int $priority = 10): void {
        self::$listeners[$event][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];
    }

    /**
     * Esegue tutti i listener di un evento
     * @param string $event Nome evento
     * @param mixed $payload Dati passati ai listener
     * @return mixed Payload modificato dai listener
     */
    public static function trigger(string $event, $payload = null) {
        if (empty(self::$listeners[$event])) {
            return $payload;
        }

        // Ordina per priorità (più basso = prima)
        $listeners = self::$listeners[$event];
        usort($listeners, fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($listeners as $listener) {
            $result = call_user_func($listener['callback'], $payload);
            if ($result !== null) {
                $payload = $result;
            }
        }

        return $payload;
    }

    /**
     * Verifica se un evento ha listener registrati
     */
    public static function has(string $event): bool {
        return !empty(self::$listeners[$event]);
    }

    /**
     * Rimuove tutti i listener di un evento
     */
    public static function off(string $event): void {
        unset(self::$listeners[$event]);
    }
}
