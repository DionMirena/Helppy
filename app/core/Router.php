<?php
declare(strict_types=1);

final class Router {
    /** @var array<int, array{method:string,pattern:string,handler:array}> */
    private array $routes = [];

    public function get(string $pattern, array $handler): void {
        $this->routes[] = ['method' => 'GET',  'pattern' => $pattern, 'handler' => $handler];
    }
    public function post(string $pattern, array $handler): void {
        $this->routes[] = ['method' => 'POST', 'pattern' => $pattern, 'handler' => $handler];
    }

    public function dispatch(string $method, string $url): void {
        $url = '/' . trim($url, '/');
        foreach ($this->routes as $r) {
            if ($r['method'] !== $method) continue;
            $regex = preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $r['pattern']);
            $regex = '#^' . $regex . '$#';
            if (preg_match($regex, $url, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                [$ctrl, $action] = $r['handler'];
                if ($method === 'POST') Request::verifyCsrf();
                $obj = new $ctrl();
                $obj->$action($params);
                return;
            }
        }
        http_response_code(404);
        View::render('errors/404', []);
    }
}
