<?php
/**
 * O-CMS — Authentication
 *
 * Handles user login/logout, role-based access control, user registration,
 * account activation, and API token management.
 *
 * @package O-CMS
 * @version 1.0.0
 */
class Auth {
    private JsonStorage $storage;

    /**
     * @param JsonStorage $storage The JSON storage engine for user data
     */
    public function __construct(JsonStorage $storage) {
        $this->storage = $storage;
    }

    /**
     * Attempt login with username and password.
     *
     * @param string $username The username to authenticate
     * @param string $password The plaintext password to verify
     * @return array Associative array with 'success' (bool) and optionally 'error' (string)
     */
    public function attempt(string $username, string $password): array {
        $user = $this->storage->find('users', $username);
        if (!$user) {
            return ['success' => false, 'error' => 'Credenziali non valide'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'error' => 'Credenziali non valide'];
        }

        // Check if the account is active
        if (isset($user['active']) && !$user['active']) {
            return ['success' => false, 'error' => 'Account non attivato. Controlla la tua email per il link di attivazione.'];
        }

        // Login successful
        Session::regenerate();
        Session::set('user', [
            'username' => $user['username'],
            'email' => $user['email'],
            'display_name' => $user['display_name'],
            'role' => $user['role'],
            'avatar' => $user['avatar'] ?? '',
        ]);

        // Update last_login timestamp
        $user['last_login'] = ocms_now();
        $this->storage->save('users', $username, $user);

        return ['success' => true];
    }

    /**
     * Log out the current user by destroying the session.
     *
     * @return void
     */
    public function logout(): void {
        Session::destroy();
    }

    /**
     * Check whether a user is currently authenticated.
     *
     * @return bool True if a user is logged in
     */
    public function check(): bool {
        return Session::get('user') !== null;
    }

    /**
     * Return the currently authenticated user's data.
     *
     * @return array|null User data array or null if not logged in
     */
    public function user(): ?array {
        return Session::get('user');
    }

    /**
     * Return the role hierarchy map (role => numeric level).
     *
     * @return array<string, int>
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
     * Return human-readable labels for each role.
     *
     * @return array<string, string>
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
     * Check whether the current user meets or exceeds a given role level.
     *
     * @param string $role The minimum role required
     * @return bool True if the user's role level is >= the required level
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
     * Require authentication and a minimum role; redirect to login or abort with 403.
     *
     * @param string $minRole The minimum role required (default: 'registered')
     * @return void
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
     * Register a new user from the frontend.
     *
     * @param array $data User data (username, email, password, display_name)
     * @return array Result with 'success', optionally 'error', 'token', and 'user'
     */
    public function register(array $data): array {
        $username = $data['username'];

        if ($this->storage->exists('users', $username)) {
            return ['success' => false, 'error' => 'Username già in uso'];
        }

        // Check for duplicate email
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
     * Activate an account via activation token.
     *
     * @param string $token The activation token sent by email
     * @return bool True if an account was found and activated
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
     * Authenticate via Bearer API token and return the token owner, or null.
     *
     * @param string $token The Bearer token from the Authorization header
     * @return array|null User data array or null if the token is invalid
     */
    public function authenticateByToken(string $token): ?array {
        $allUsers = $this->storage->findAll('users');
        foreach ($allUsers as $u) {
            // Skip explicitly deactivated users (active=false); users without the field are considered active
            if (isset($u['active']) && !$u['active']) continue;
            foreach ($u['api_tokens'] ?? [] as &$t) {
                if (hash_equals($t['token'], $token)) {
                    // Update last_used timestamp
                    $t['last_used'] = ocms_now();
                    $this->storage->save('users', $u['username'], $u);
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
     * Generate a new API token for a user.
     *
     * @param string $username The username to create a token for
     * @param string $name A human-readable name/label for the token
     * @return array|null The created token entry, or null if the user was not found
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
     * Revoke an API token by its ID.
     *
     * @param string $username The user who owns the token
     * @param string $tokenId The unique ID of the token to revoke
     * @return bool True on success
     */
    public function revokeApiToken(string $username, string $tokenId): bool {
        $user = $this->storage->find('users', $username);
        if (!$user) return false;

        $tokens = $user['api_tokens'] ?? [];
        $user['api_tokens'] = array_values(array_filter($tokens, fn($t) => $t['id'] !== $tokenId));
        return $this->storage->save('users', $username, $user);
    }

    /**
     * Return the API tokens for a user (token values are masked for security).
     *
     * @param string $username The username to retrieve tokens for
     * @return array List of token entries with masked token hints
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
     * Create a new user from the admin panel.
     *
     * @param array $data User data (username, email, password, display_name, role)
     * @return bool True on success, false if the username already exists
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
