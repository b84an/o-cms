<?php
/**
 * O-CMS — Hook / Event System
 *
 * Provides a simple publish-subscribe mechanism that allows extensions
 * and plugins to hook into CMS lifecycle events.
 *
 * @package O-CMS
 * @version 1.0.0
 */
class Hooks {
    /** @var array<string, array> Registered event listeners keyed by event name */
    private static array $listeners = [];

    /**
     * Register a listener for an event.
     *
     * @param string   $event    Event name (e.g. 'article.saved', 'extension.booted')
     * @param callable $callback Callback to invoke when the event fires
     * @param int      $priority Lower numbers execute first (default: 10)
     * @return void
     */
    public static function on(string $event, callable $callback, int $priority = 10): void {
        self::$listeners[$event][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];
    }

    /**
     * Trigger all listeners for an event, passing and returning a payload.
     *
     * Listeners are executed in priority order (lowest first). Each listener
     * may modify and return the payload; non-null return values replace it.
     *
     * @param string $event   Event name
     * @param mixed  $payload Data passed to (and potentially modified by) listeners
     * @return mixed The final payload after all listeners have processed it
     */
    public static function trigger(string $event, $payload = null) {
        if (empty(self::$listeners[$event])) {
            return $payload;
        }

        // Sort by priority (lower = earlier)
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
     * Check whether an event has any registered listeners.
     *
     * @param string $event Event name
     * @return bool
     */
    public static function has(string $event): bool {
        return !empty(self::$listeners[$event]);
    }

    /**
     * Remove all listeners for an event.
     *
     * @param string $event Event name
     * @return void
     */
    public static function off(string $event): void {
        unset(self::$listeners[$event]);
    }
}
