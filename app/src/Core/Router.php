<?php

/**
 * RUND API - Router Moderno
 *
 * Sistema de enrutamiento flexible con soporte para middleware,
 * versionado de API y controllers separados.
 *
 * @author ESAP Development Team / Oliver Castelblanco Martínez
 * @version 3.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Core;

class Router
{
	private array $routes = [];
	private array $middleware = [];
	private string $basePath = '';

	public function __construct(string $basePath = '')
	{
		$this->basePath = rtrim($basePath, '/');
	}

	/**
	 * Registra una ruta GET
	 */
	public function get(string $path, callable|array $handler, array $middleware = []): self
	{
		return $this->addRoute('GET', $path, $handler, $middleware);
	}

	/**
	 * Registra una ruta POST
	 */
	public function post(string $path, callable|array $handler, array $middleware = []): self
	{
		return $this->addRoute('POST', $path, $handler, $middleware);
	}

	/**
	 * Registra una ruta PUT
	 */
	public function put(string $path, callable|array $handler, array $middleware = []): self
	{
		return $this->addRoute('PUT', $path, $handler, $middleware);
	}

	/**
	 * Registra una ruta DELETE
	 */
	public function delete(string $path, callable|array $handler, array $middleware = []): self
	{
		return $this->addRoute('DELETE', $path, $handler, $middleware);
	}

	/**
	 * Registra una ruta para cualquier método HTTP
	 */
	public function any(string $path, callable|array $handler, array $middleware = []): self
	{
		$methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
		foreach ($methods as $method) {
			$this->addRoute($method, $path, $handler, $middleware);
		}
		return $this;
	}

	/**
	 * Añade middleware global que se ejecutará en todas las rutas
	 */
	public function addGlobalMiddleware(callable $middleware): self
	{
		$this->middleware[] = $middleware;
		return $this;
	}

	/**
	 * Agrupa rutas bajo un prefijo común
	 */
	public function group(string $prefix, callable $callback): self
	{
		$originalBasePath = $this->basePath;
		$this->basePath = $originalBasePath . '/' . ltrim($prefix, '/');

		$callback($this);

		$this->basePath = $originalBasePath;
		return $this;
	}

	/**
	 * Procesa la solicitud HTTP actual
	 */
	public function dispatch(): void
	{
		$method = $_SERVER['REQUEST_METHOD'];
		$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$path = $this->normalizePath($uri);

		// Ejecutar middleware global
		foreach ($this->middleware as $middleware) {
			$result = $middleware($method, $path);
			if ($result === false) {
				return; // Middleware interrumpió la ejecución
			}
		}

		// Buscar ruta coincidente
		$route = $this->findRoute($method, $path);

		if (!$route) {
			$this->handleNotFound();
			return;
		}

		// Ejecutar middleware específico de la ruta
		foreach ($route['middleware'] as $middleware) {
			$result = $middleware($method, $path, $route['params']);
			if ($result === false) {
				return; // Middleware interrumpió la ejecución
			}
		}

		// Ejecutar el handler
		$this->executeHandler($route);
	}

	/**
	 * Registra una ruta internamente
	 */
	private function addRoute(string $method, string $path, callable|array $handler, array $middleware): self
	{
		$normalizedPath = $this->normalizePath($this->basePath . '/' . ltrim($path, '/'));
		$pattern = $this->pathToRegex($normalizedPath);

		$this->routes[] = [
			'method' => $method,
			'path' => $normalizedPath,
			'pattern' => $pattern,
			'handler' => $handler,
			'middleware' => $middleware
		];

		return $this;
	}

	/**
	 * Normaliza una ruta eliminando barras duplicadas
	 */
	private function normalizePath(string $path): string
	{
		$path = str_replace('/index.php', '', $path);
		$path = preg_replace('#/+#', '/', $path);
		return rtrim($path, '/') ?: '/';
	}

	/**
	 * Convierte una ruta en expresión regular para matching con parámetros
	 */
	private function pathToRegex(string $path): string
	{
		// Escapar caracteres especiales excepto los parámetros
		$pattern = preg_quote($path, '#');
		// Convertir {param} en grupo de captura
		$pattern = preg_replace('#\\\\{(\w+)\\\\}#', '([^/]+)', $pattern);
		return "#^{$pattern}$#";
	}

	/**
	 * Busca una ruta que coincida con el método y path actual
	 */
	private function findRoute(string $method, string $path): ?array
	{
		foreach ($this->routes as $route) {
			if ($route['method'] !== $method) {
				continue;
			}

			if (preg_match($route['pattern'], $path, $matches)) {
				// Extraer parámetros de la URL
				$params = [];
				if (count($matches) > 1) {
					// Los parámetros capturados están en $matches[1], $matches[2], etc.
					$pathParts = explode('/', trim($route['path'], '/'));
					$matchIndex = 1;
					foreach ($pathParts as $part) {
						if (preg_match('/^{(\w+)}$/', $part, $paramMatch)) {
							// Decodificar automáticamente los parámetros de URL
							$params[$paramMatch[1]] = urldecode($matches[$matchIndex++]);
						}
					}
				}

				$route['params'] = $params;
				return $route;
			}
		}

		return null;
	}

	/**
	 * Ejecuta el handler de una ruta
	 */
	private function executeHandler(array $route): void
	{
		$handler = $route['handler'];

		if (is_callable($handler)) {
			// Handler es una función
			$result = $handler($route['params']);
		} elseif (is_array($handler) && count($handler) === 2) {
			// Handler es [Controller::class, 'method']
			[$controllerClass, $method] = $handler;

			if (!class_exists($controllerClass)) {
				throw new \Exception("Controller class {$controllerClass} not found");
			}

			$controller = new $controllerClass();

			if (!method_exists($controller, $method)) {
				throw new \Exception("Method {$method} not found in {$controllerClass}");
			}

			$result = $controller->{$method}($route['params']);
		} else {
			throw new \Exception("Invalid handler format");
		}

		// Manejar la respuesta
		$this->handleResponse($result);
	}

	/**
	 * Maneja la respuesta del handler
	 */
	private function handleResponse($result): void
	{
		if ($result === null) {
			// El handler se encargó de la respuesta (ej: archivos, redirects)
			// No hacer nada más, el handler ya envió headers y contenido
			return;
		}

		if (is_array($result)) {
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		} elseif (is_string($result)) {
			// Para strings simples, asumir text/plain a menos que se haya establecido otro header
			if (!headers_sent() && !headers_list()) {
				header('Content-Type: text/plain; charset=utf-8');
			}
			echo $result;
		} else {
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode(['data' => $result], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		}
	}

	/**
	 * Maneja rutas no encontradas
	 */
	private function handleNotFound(): void
	{
		http_response_code(404);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode([
			'error' => 'Endpoint no encontrado',
			'method' => $_SERVER['REQUEST_METHOD'],
			'path' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
		], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
	}
}
