<?php
/**
 * O-CMS — Autenticazione
 */
class Auth {
    private JsonStorage $storage;
    private ?array $freshUser = null;

    public function __construct(JsonStorage $storage) {
        $this->storage = $storage;
    }

    /**
     * Tenta il login con username e password
     */
    public function attempt(string $username, string $password): array {
        $user = $this->storage->find('users', $username);
        if (!$user) {
            return ['success' => false, 'error' => 'Credenziali non valide'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'error' => 'Credenziali non valide'];
        }

        // Controlla se l'account è attivo
        if (isset($user['active']) && !$user['active']) {
            return ['success' => false, 'error' => 'Account non attivato. Controlla la tua email per il link di attivazione.'];
        }

        // Login riuscito
        Session::regenerate();
        Session::set('user', [
            'username' => $user['username'],
            'email' => $user['email'],
            'display_name' => $user['display_name'],
            'role' => $user['role'],
            'avatar' => $user['avatar'] ?? '',
        ]);

        // Aggiorna last_login
        $user['last_login'] = ocms_now();
        $this->storage->save('users', $username, $user);

        return ['success' => true];
    }

    /**
     * Effettua il logout
     */
    public function logout(): void {
        Session::destroy();
    }

    /**
     * Verifica se l'utente è loggato
     */
    public function check(): bool {
        return $this->user() !== null;
    }

    /**
     * Restituisce i dati dell'utente loggato
     */
    public function user(): ?array {
        $sessionUser = Session::get('user');
        if (!$sessionUser) return null;

        // Una volta per richiesta: ricarica ruolo e stato dal file utente.
        // Così un utente eliminato, disattivato o declassato perde subito i permessi.
        if ($this->freshUser === null || ($this->freshUser['username'] ?? null) !== ($sessionUser['username'] ?? null)) {
            $stored = $this->storage->find('users', (string)($sessionUser['username'] ?? ''));
            if (!$stored || (isset($stored['active']) && !$stored['active'])) {
                Session::remove('user');
                $this->freshUser = null;
                return null;
            }
            $fresh = [
                'username' => $stored['username'],
                'email' => $stored['email'] ?? '',
                'display_name' => $stored['display_name'] ?? $stored['username'],
                'role' => $stored['role'] ?? 'registered',
                'avatar' => $stored['avatar'] ?? '',
            ];
            if ($fresh != $sessionUser) {
                Session::set('user', $fresh);
            }
            $this->freshUser = $fresh;
        }
        return $this->freshUser;
    }

    /**
     * Gerarchia ruoli
     */
    public function getRoleHierarchy(): array {
        return [
            'super_administrator' => 5,
            'administrator' => 4,
            'editor' => 3,
            'publisher' => 2,
            'registered' => 1,
        ];
    }

    /**
     * Nomi leggibili dei ruoli
     */
    public static function getRoleLabels(): array {
        return [
            'super_administrator' => 'Super Amministratore',
            'administrator' => 'Amministratore',
            'editor' => 'Editor',
            'publisher' => 'Publisher',
            'registered' => 'Registrato',
        ];
    }

    /**
     * Verifica se l'utente ha un determinato ruolo
     */
    public function hasRole(string $role): bool {
        $user = $this->user();
        if (!$user) return false;

        $hierarchy = $this->getRoleHierarchy();

        $userLevel = $hierarchy[$user['role']] ?? 0;
        $requiredLevel = $hierarchy[$role] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    /**
     * Richiede autenticazione, altrimenti redirect al login
     */
    public function requireRole(string $minRole = 'registered'): void {
        if (!$this->check()) {
            ocms_redirect(ocms_base_url() . '/admin/login');
        }
        if (!$this->hasRole($minRole)) {
            http_response_code(403);
            echo 'Accesso negato';
            exit;
        }
    }

    /**
     * Registra un nuovo utente (frontend)
     */
    public function register(array $data): array {
        $username = $data['username'];

        if ($this->storage->exists('users', $username)) {
            return ['success' => false, 'error' => 'Username già in uso'];
        }

        // Controlla email duplicata
        $allUsers = $this->storage->findAll('users');
        foreach ($allUsers as $u) {
            if (strtolower($u['email']) === strtolower($data['email'])) {
                return ['success' => false, 'error' => 'Email già registrata'];
            }
        }

        $token = bin2hex(random_bytes(32));

        $user = [
            'id' => ocms_uuid(),
            'username' => $username,
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'display_name' => $data['display_name'] ?? $username,
            'role' => 'registered',
            'avatar' => '',
            'active' => false,
            'activation_token' => $token,
            'created_at' => ocms_now(),
            'last_login' => null,
        ];

        $this->storage->save('users', $username, $user);

        return ['success' => true, 'token' => $token, 'user' => $user];
    }

    /**
     * Attiva un account tramite token
     */
    public function activate(string $token): bool {
        $allUsers = $this->storage->findAll('users');
        foreach ($allUsers as $u) {
            if (($u['activation_token'] ?? '') === $token && !($u['active'] ?? true)) {
                $u['active'] = true;
                $u['activation_token'] = '';
                $this->storage->save('users', $u['username'], $u);
                return true;
            }
        }
        return false;
    }

    /**
     * Autentica via API token Bearer — restituisce l'utente proprietario o null
     */
    public function authenticateByToken(string $token): ?array {
        $allUsers = $this->storage->findAll('users');
        foreach ($allUsers as $u) {
            // Utenti esplicitamente disattivati (active=false); utenti senza il campo sono considerati attivi
            if (isset($u['active']) && !$u['active']) continue;
            foreach (($u['api_tokens'] ?? []) as $i => $t) {
                if (hash_equals((string)($t['token'] ?? ''), $token)) {
                    // Aggiorna last_used al massimo ogni 10 minuti, per non riscrivere
                    // il file utente a ogni chiamata API
                    $last = !empty($t['last_used']) ? strtotime($t['last_used']) : 0;
                    if (time() - (int)$last > 600) {
                        $this->storage->update('users', $u['username'], function ($d) use ($i, $token) {
                            if (!$d || !isset($d['api_tokens'][$i]) || !hash_equals((string)$d['api_tokens'][$i]['token'], $token)) return null;
                            $d['api_tokens'][$i]['last_used'] = ocms_now();
                            return $d;
                        });
                    }
                    return [
                        'username' => $u['username'],
                        'email' => $u['email'],
                        'display_name' => $u['display_name'],
                        'role' => $u['role'],
                        'avatar' => $u['avatar'] ?? '',
                    ];
                }
            }
        }
        return null;
    }

    /**
     * Genera un nuovo API token per un utente
     */
    public function createApiToken(string $username, string $name): ?array {
        $user = $this->storage->find('users', $username);
        if (!$user) return null;

        $token = bin2hex(random_bytes(32));
        $entry = [
            'id' => ocms_uuid(),
            'name' => $name,
            'token' => $token,
            'created_at' => ocms_now(),
            'last_used' => null,
        ];

        $user['api_tokens'] = $user['api_tokens'] ?? [];
        $user['api_tokens'][] = $entry;
        $this->storage->save('users', $username, $user);

        return $entry;
    }

    /**
     * Revoca un API token
     */
    public function revokeApiToken(string $username, string $tokenId): bool {
        $user = $this->storage->find('users', $username);
        if (!$user) return false;

        $tokens = $user['api_tokens'] ?? [];
        $user['api_tokens'] = array_values(array_filter($tokens, fn($t) => $t['id'] !== $tokenId));
        return $this->storage->save('users', $username, $user);
    }

    /**
     * Restituisce i token API di un utente (senza il token in chiaro per sicurezza)
     */
    public function getApiTokens(string $username): array {
        $user = $this->storage->find('users', $username);
        if (!$user) return [];

        return array_map(function ($t) {
            return [
                'id' => $t['id'],
                'name' => $t['name'],
                'token_hint' => substr($t['token'], 0, 8) . '...',
                'created_at' => $t['created_at'],
                'last_used' => $t['last_used'],
            ];
        }, $user['api_tokens'] ?? []);
    }

    /**
     * Crea un nuovo utente (admin)
     */
    public function createUser(array $data): bool {
        $username = $data['username'];
        if ($this->storage->exists('users', $username)) {
            return false;
        }

        $user = [
            'id' => ocms_uuid(),
            'username' => $username,
            'email' => $data['email'] ?? '',
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'display_name' => $data['display_name'] ?? $username,
            'role' => $data['role'] ?? 'registered',
            'avatar' => '',
            'active' => true,
            'created_at' => ocms_now(),
            'last_login' => null,
        ];

        return $this->storage->save('users', $username, $user);
    }
}
