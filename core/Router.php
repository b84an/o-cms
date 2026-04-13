<?php
/**
 * O-CMS — Router
 *
 * Routes incoming HTTP requests to the appropriate handler callbacks.
 * Supports named parameters via {name} placeholders in URL patterns.
 *
 * @package O-CMS
 * @version 1.0.0
 */
class Router {
    private array $routes = [];
    private string $basePath;

    /**
     * @param string $basePath The URL base path to strip before matching (e.g. '/s')
     */
    public function __construct(string $basePath = '') {
        $this->basePath = $basePath;
    }

    /**
     * Register a GET route.
     *
     * @param string   $pattern URL pattern with optional {param} placeholders
     * @param callable $handler Callback receiving matched parameters
     * @return self
     */
    public function get(string $pattern, callable $handler): self {
        $this->routes[] = ['method' => 'GET', 'pattern' => $pattern, 'handler' => $handler];
        return $this;
    }

    /**
     * Register a POST route.
     *
     * @param string   $pattern URL pattern with optional {param} placeholders
     * @param callable $handler Callback receiving matched parameters
     * @return self
     */
    public function post(string $pattern, callable $handler): self {
        $this->routes[] = ['method' => 'POST', 'pattern' => $pattern, 'handler' => $handler];
        return $this;
    }

    /**
     * Register a route matching any HTTP method.
     *
     * @param string   $pattern URL pattern with optional {param} placeholders
     * @param callable $handler Callback receiving matched parameters
     * @return self
     */
    public function any(string $pattern, callable $handler): self {
        $this->routes[] = ['method' => 'ANY', 'pattern' => $pattern, 'handler' => $handler];
        return $this;
    }

    /**
     * Dispatch the current HTTP request against registered routes.
     *
     * @return void
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

        // No matching route found — 404
        $this->notFound();
    }

    /**
     * Extract the URI from the request, stripping the basePath and query string.
     *
     * @return string The cleaned URI path
     */
    private function getUri(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // Remove query string
        $uri = strtok($uri, '?');

        // Remove base path
        if ($this->basePath && strpos($uri, $this->basePath) === 0) {
            $uri = substr($uri, strlen($this->basePath));
        }

        $uri = '/' . trim($uri, '/');
        return $uri === '' ? '/' : $uri;
    }

    /**
     * Match a URL pattern against the given URI.
     * Supports named parameters via {name} placeholders.
     *
     * @param string $pattern The route pattern to match
     * @param string $uri     The request URI to test
     * @return array|false Named parameters array on match, or false
     */
    private function match(string $pattern, string $uri): array|false {
        // Convert pattern to regex
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Keep only named parameters
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return false;
    }

    /**
     * Render a 404 Not Found response.
     *
     * @return void
     */
    private function notFound(): void {
        http_response_code(404);
        // TODO: render the active theme's 404 template
        echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>Pagina non trovata</h1></body></html>';
    }
}
