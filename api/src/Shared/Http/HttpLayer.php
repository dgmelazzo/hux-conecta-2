<?php declare(strict_types=1);

// ============================================================
// Request.php
// ============================================================

namespace CRM\Shared\Http;

class Request
{
    private array $user   = [];
    private array $tenant = [];

    public function json(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw ?: '{}', true) ?? [];
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? $_SERVER[$name] ?? null;
    }

    public function headers(): array
    {
        return array_filter($_SERVER, fn($k) => str_starts_with($k, 'HTTP_'), ARRAY_FILTER_USE_KEY);
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    }

    public function setUser(array $user): void  { $this->user   = $user; }
    public function setTenant(array $t): void   { $this->tenant = $t;    }
    public function user(): array               { return $this->user;    }
    public function tenant(): array             { return $this->tenant;  }
    public function tenantId(): ?int            { return $this->user['tenant_id'] ?? null; }
}


// ============================================================
// Response.php
// ============================================================

namespace CRM\Shared\Http;

class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function noContent(): void
    {
        http_response_code(204);
    }

    public static function notFound(string $msg = 'Recurso não encontrado.'): void
    {
        self::json(['error' => $msg], 404);
    }

    public static function serverError(string $msg = 'Erro interno.'): void
    {
        self::json(['error' => $msg], 500);
    }
}


// ============================================================
// Router.php — roteador simples com suporte a middleware
// ============================================================

namespace CRM\Shared\Http;

class Router
{
    private array $routes     = [];
    private array $middleware = [];

    public function get(string $path, callable $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): self
    {
        return $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, callable $handler): self
    {
        return $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, callable $handler): self
    {
        return $this->add('DELETE', $path, $handler);
    }

    public function middleware(callable $mw): self
    {
        $this->middleware[] = $mw;
        return $this;
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path   = rtrim($request->path(), '/') ?: '/';

        foreach ($this->routes as $route) {
            [$rMethod, $rPath, $handler] = $route;

            if ($rMethod !== $method) continue;

            $params = $this->match($rPath, $path);
            if ($params === null) continue;

            // Encadear middlewares
            $chain = array_reduce(
                array_reverse($this->middleware),
                fn($next, $mw) => fn($req) => $mw($req, $next),
                fn($req) => $handler($req, ...$params),
            );

            $chain($request);
            return;
        }

        Response::notFound("Rota {$method} {$path} não encontrada.");
    }

    private function add(string $method, string $path, callable $handler): self
    {
        $this->routes[] = [$method, $path, $handler];
        return $this;
    }

    /** Extrai parâmetros de rota. Ex: /associados/{id} → ['123'] */
    private function match(string $route, string $path): ?array
    {
        $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $route);
        $pattern = "#^{$pattern}$#";

        if (!preg_match($pattern, $path, $matches)) return null;

        array_shift($matches);
        return $matches;
    }
}
