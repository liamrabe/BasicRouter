<?php
namespace LiamRabe\BasicRouter;

use LiamRabe\BasicRouter\DataCollection\RouteCollection;
use LiamRabe\BasicRouter\Trait\SemanticVersionTrait;
use LiamRabe\BasicRouter\DataCollection\Response;
use LiamRabe\BasicRouter\Exception\HttpException;
use LiamRabe\BasicRouter\DataCollection\Request;
use LiamRabe\BasicRouter\Trait\LifecycleTrait;
use LiamRabe\BasicRouter\Route\AbstractRoute;
use LiamRabe\BasicRouter\Route\Route;
use InvalidArgumentException;
use RuntimeException;

class Router {
	use LifecycleTrait, SemanticVersionTrait;

	protected const VERSION = '2.1.2';

	private string $route = Route::class;
	private array $middlewares;
	private array $controller;

	protected bool $expose_router = true;

	protected string $uri = '';

	public function setExposeRouter(bool $expose_router): void {
		$this->expose_router = $expose_router;
	}

	/**
	 * Validate router default value
	 *
	 * @throws InvalidArgumentException
	 */
	private function validateDefaultValue(string $class, ?string $method = null): void {
		if (!class_exists($class)) {
			throw new InvalidArgumentException(sprintf("The class '%s' wasn't found", $class));
		}

		if ($method !== null && !method_exists($class, $method)) {
			throw new InvalidArgumentException(sprintf("The method '%s' in class '%s' doesn't exist", $class, $method));
		}
	}

	/**
	 * Validate existence of middleware & error controller before adding route
	 *
	 * @throws RuntimeException
	 */
	private function validateRouteCreation(): void {
		$middleware = $this->middlewares ?? null;
		if (!$middleware) {
			throw new RuntimeException('Middleware required before creating routes', 500);
		}

		$controller = $this->controller ?? null;
		if (!$controller) {
			throw new RuntimeException('Error controller required before creating routes', 500);
		}
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function setDefaultErrorController(string $class, string $method): void {
		$this->validateDefaultValue($class, $method);

		$this->controller = [ $class, $method ];
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function setDefaultMiddleware(string|array $class, string $method = null): void {
		if (is_array($class)) {
			foreach ($class as $middleware) {
				$this->setDefaultMiddleware($middleware[0] ?? '', $middleware[1] ?? '');
			}

			return;
		}

		$this->validateDefaultValue($class, $method);

		$this->middlewares[] = [ $class, $method ];
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function setDefaultRoute(string $class): void {
		$this->validateDefaultValue($class);

		if (!new $class() instanceof AbstractRoute) {
			throw new InvalidArgumentException("Route has to be an instance of class '%s'", Route::class);
		}

		$this->route = $class;
	}

	protected function getMethod(): string {
		return $_SERVER[ 'REQUEST_METHOD' ];
	}

	protected function getURI(): string {
		$parts = explode('?', $_SERVER[ 'REQUEST_URI' ]);
		return array_shift($parts);
	}

	/**
	 * Redirect user to specified target
	 */
	public function redirect(string $uri, string $target, int $code = 301): void {
		if ($this->getURI() === $uri) {
			header(sprintf('Location: %s', $target), true, $code);
			exit;
		}
	}

	/**
	 * @throws InvalidArgumentException
	 */
	private function route(string $method, string $uri, string|array|callable $callback): AbstractRoute {
		$this->validateRouteCreation();

		if ($this->uri !== '') {
			$uri = sprintf('%s/%s', $this->uri, ltrim($uri, '/'));
		}

		return RouteCollection::addRoute(new $this->route(
			$method,
			$uri,
			$callback,
			$this->middlewares
		));
	}

	public function get(string $uri, string|array|callable $callback): AbstractRoute {
		return $this->route('GET', $uri, $callback);
	}

	public function put(string $uri, string|array|callable $callback): AbstractRoute {
		return $this->route('PUT', $uri, $callback);
	}

	public function post(string $uri, string|array|callable $callback): AbstractRoute {
		return $this->route('POST', $uri, $callback);
	}

	public function delete(string $uri, string|array|callable $callback): AbstractRoute {
		return $this->route('DELETE', $uri, $callback);
	}

	public function all(string $uri, string|array|callable $callback): AbstractRoute {
		return $this->route('ALL', $uri, $callback);
	}

	public function group(string $prefix, Callable $group_callable, array $group_middlewares = []): void {
		$this->uri .= $prefix;

		$cache_middlewares = $this->middlewares;
		$this->setDefaultMiddleware($group_middlewares);

		call_user_func_array($group_callable, [ $this ]);

		$this->setDefaultMiddleware($cache_middlewares);
		unset($cache_middlewares);
	}

	public function run(): void {
		try {
			$routes = RouteCollection::getRoutes($this->getMethod(), $this->getURI());

			/** @var Route $route */
			$route = end($routes);

			if (empty($matched_routes) && is_bool($route) && !$route) {
				throw new HttpException(sprintf(
					"The requested URI '%s' doesn't exist",
					$this->getURI(),
				), 404);
			}

			$request = Request::createFromGlobals();
			$callback = $route->getCallback();

			/* Run middleware */
			$middleware_result = true;

			foreach ($this->middlewares as $middleware) {
				$current_middleware_result = call_user_func($middleware);

				if (!$current_middleware_result) {
					$middleware_result = false;
				}
			}

			if (!$middleware_result) {
				return;
			}

			$arguments = $route->getParameters($this->getURI());
			$request->setParameters($arguments);

			$response = new Response();

			if (!method_exists($callback[0] ?? '', $callback[1] ?? '')) {
				throw new RuntimeException(sprintf(
					"Callback method '%s' doesn't exist in class %s",
					$callback[1] ?? '',
					$callback[0] ?? '',
				), 500);
			} else {
				/** @var Response $response */
				$response = call_user_func_array($callback, [$request, $response]);
			}

			$this->handleOutput($route, $request, $response);

		} catch (HttpException|RuntimeException $ex) {
			$response = call_user_func_array($this->controller, [ $ex ]);

			$this->handleOutput(null, null, $response, true);
		}
	}

	/**
	 * @throws RuntimeException
	 */
	protected function handleOutput(?Route $route = null, ?Request $request = null, ?object $response = null, bool $is_error = false): void {
		if (!$response instanceof Response) {
			throw new RuntimeException(sprintf(
				"Response has to be instance of class '%s'",
				Response::class,
			), 500);
		}

		/* Process response from controller */
		http_response_code($response->getStatus());

		if (!$this->skip_lifecycle && !$is_error) {
			$this->preHeaders($route, $request, $response);
		}

		foreach ($response->getHeaders() as $header => $value) {
			header(sprintf('%s: %s', $header, $value), true, $response->getStatus());
		}

		if (!$this->skip_lifecycle && !$is_error) {
			$this->postHeaders($route, $request, $response);
			$this->preWrite($route, $request, $response);
		}

		echo $response->getBody();

		if (!$this->skip_lifecycle && !$is_error) {
			$this->postWrite($route, $request, $response);
		}
	}

}
