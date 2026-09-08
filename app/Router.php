<?php
/**
 * KanbanDoo - Roteador
 *
 * Tabela de rotas simples: cada entrada aponta para o arquivo de um módulo e
 * declara sozinha quem pode acessá-la. Acrescentar uma tela é acrescentar uma
 * linha em routes.php — sem tocar no que já funciona.
 */

final class Router
{
    /** @var array<string, array> */
    private array $routes = [];
    private array $redirects = [];

    /**
     * @param string $path    Caminho da rota; ":param" captura um segmento.
     * @param string $file    Arquivo do módulo, relativo a app/.
     * @param array  $options auth => bool (padrão true), admin => bool, methods => string[]
     */
    public function add(string $path, string $file, array $options = []): void
    {
        $this->routes[$this->normalize($path)] = [
            'file' => $file,
            'auth' => $options['auth'] ?? true,
            'admin' => $options['admin'] ?? false,
            'methods' => $options['methods'] ?? ['GET', 'POST'],
        ];
    }

    /** Endereço antigo que deve continuar levando ao lugar certo. */
    public function legacy(string $path, string $target): void
    {
        $this->redirects[$this->normalize($path)] = $target;
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    /**
     * Encontra a rota do caminho pedido e devolve [rota, parâmetros].
     * @return array{0: ?array, 1: array<string,string>}
     */
    public function match(string $path): array
    {
        $path = $this->normalize($path);

        if (isset($this->routes[$path])) {
            return [$this->routes[$path], []];
        }

        $parts = explode('/', trim($path, '/'));

        foreach ($this->routes as $pattern => $route) {
            if (!str_contains($pattern, ':')) {
                continue;
            }

            $patternParts = explode('/', trim($pattern, '/'));
            if (count($patternParts) !== count($parts)) {
                continue;
            }

            $params = [];
            $matches = true;

            foreach ($patternParts as $index => $segment) {
                if (str_starts_with($segment, ':')) {
                    $params[substr($segment, 1)] = $parts[$index];
                    continue;
                }
                if ($segment !== $parts[$index]) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                return [$route, $params];
            }
        }

        return [null, []];
    }

    public function legacyTarget(string $path): ?string
    {
        return $this->redirects[$this->normalize($path)] ?? null;
    }

    /** Executa a rota do caminho atual. */
    public function dispatch(string $path): void
    {
        $legacy = $this->legacyTarget($path);
        if ($legacy !== null) {
            header('Location: ' . url($legacy) . $this->queryString(), true, 301);
            exit;
        }

        [$route, $params] = $this->match($path);

        if ($route === null) {
            $this->notFound();
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, $route['methods'], true)) {
            http_response_code(405);
            header('Allow: ' . implode(', ', $route['methods']));
            echo 'Método não permitido.';
            return;
        }

        if ($route['auth']) {
            require_login();
        }
        if ($route['admin']) {
            require_admin();
        }

        // Disponível para o módulo como $routeParams
        $routeParams = $params;
        require KD_ROOT . '/app/' . ltrim($route['file'], '/');
    }

    private function queryString(): string
    {
        $query = $_SERVER['QUERY_STRING'] ?? '';
        return $query === '' ? '' : '?' . $query;
    }

    private function notFound(): void
    {
        http_response_code(404);

        // Requisições de API respondem em JSON; páginas mostram a tela de erro.
        if (str_starts_with(current_path(), '/api/')) {
            json_response(['error' => 'Endpoint não encontrado.'], 404);
        }

        require KD_ROOT . '/app/pages/404.php';
    }
}
