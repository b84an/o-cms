<?php
/**
 * O-CMS — Router
 *
 * Gestisce il routing delle richieste URL verso i controller appropriati.
 */
class Router {
    private array $routes = [];
    private string $basePath;

    public function __construct(string $basePath = '') {
        $this->basePath = $basePath;
    }

    /**
     * Registra una rotta GET
     */
    public function get(string $pattern, callable $handler): self {
        $this->routes[] = ['method' => 'GET', 'pattern' => $pattern, 'handler' => $handler];
        return $this;
    }

    /**
     * Registra una rotta POST
     */
    public function post(string $pattern, callable $handler): self {
        $this->routes[] = ['method' => 'POST', 'pattern' => $pattern, 'handler' => $handler];
        return $this;
    }

    /**
     * Registra una rotta per qualsiasi metodo
     */
    public function any(string $pattern, callable $handler): self {
        $this->routes[] = ['method' => 'ANY', 'pattern' => $pattern, 'handler' => $handler];
        return $this;
    }

    /**
     * Dispatch della richiesta corrente
     */
    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $this->getUri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                continue;
            }

            $params = $this->match($route['pattern'], $uri);
            if ($params !== false) {
                call_user_func($route['handler'], $params);
                return;
            }
        }

        // Nessuna rotta trovata — 404
        $this->notFound();
    }

    /**
     * Estrae l'URI dalla richiesta, rimuovendo il basePath e query string
     */
    private function getUri(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Rimuovi query string
        $uri = strtok($uri, '?');

        // Rimuovi base path
        if ($this->basePath && strpos($uri, $this->basePath) === 0) {
            $uri = substr($uri, strlen($this->basePath));
        }

        $uri = '/' . trim($uri, '/');
        return $uri === '' ? '/' : $uri;
    }

    /**
     * Verifica se un pattern corrisponde all'URI
     * Supporta parametri con {name}
     * Restituisce array di parametri o false
     */
    private function match(string $pattern, string $uri): array|false {
        // Converti pattern in regex
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Filtra solo i parametri nominati
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            // Difesa centrale: un parametro "." o ".." (anche codificato) non è mai uno slug
            // valido e, finito in un percorso, farebbe risalire di cartella
            foreach ($params as $v) {
                $d = rawurldecode((string)$v);
                if ($d === '.' || $d === '..' || str_contains($d, "\0") || str_contains($d, '/') || str_contains($d, '\\')) {
                    return false;
                }
            }
            return $params;
        }

        return false;
    }

    /**
     * Risposta 404
     */
    private function notFound(): void {
        http_response_code(404);
        // TODO: renderizzare template 404 del tema attivo
        echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>Pagina non trovata</h1></body></html>';
    }
}
