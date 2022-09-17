<?php
namespace LiamRabe\BasicRouter;

use LiamRabe\BasicRouter\DataCollection\Response;
use LiamRabe\BasicRouter\Exception\HttpException;
use LiamRabe\BasicRouter\DataCollection\Request;
use JetBrains\PhpStorm\ArrayShape;
use InvalidArgumentException;
use RuntimeException;

class Router {

	protected const VERSION = '2.1.0';

	protected static array $controller;
	protected static array $middleware;

	protected static bool $skip_lifecycle = true;
	protected static bool $expose_router = true;

	/** @var Route[] $routes */
	protected static array $routes = [];
	protected static string $uri = '';

	/** @throws InvalidArgumentException */
	public static function setMiddleware(string $middleware, string $method): void {
		if (!class_exists($middleware)) {
			throw new InvalidArgumentException(sprintf(
				"Class '%s' doesn't exist",
				$middleware,
			));
		}

		if (!method_exists($middleware, $method)) {
			throw new InvalidArgumentException(sprintf(
				"Method '%s' doesn't exist in class %s",
				$method,
				$middleware
			));
		}

		static::$middleware = [
			$middleware,
			$method,
		];
	}

	/** @throws InvalidArgumentException */
	public static function setErrorController(string $controller, string $method): void {
		if (!class_exists($controller)) {
			throw new InvalidArgumentException(sprintf(
				"Class '%s' doesn't exist",
				$controller,
			));
		}

		if (!method_exists($controller, $method)) {
			throw new InvalidArgumentException(sprintf(
				"Method '%s' doesn't exist in class %s",
				$method,
				$controller
			));
		}

		static::$controller = [
			$controller,
			$method,
		];
	}

	public static function setExposeRouter(bool $expose): void {
		static::$expose_router = $expose;
	}

	public static function getExposeRouter(): bool {
		return static::$expose_router;
	}

	public static function redirect(string $uri, string $target, int $code = 301): void {
		if (static::getURI() === $uri) {
			header(sprintf('Location: %s', $target), true,  $code);
			exit;
		}
	}

	/** @throws RuntimeException */
	protected static function route(string $method, string $uri, string|array|callable $callback): Route {
		if (static::$uri !== '') {
			$uri = sprintf('%s/%s', static::$uri, ltrim($uri, '/'));
		}

		$middleware = static::$middleware ?? null;
		if (!$middleware) {
			throw new RuntimeException('Middleware required before creating routes', 500);
		}

		$controller = static::$controller ?? null;
		if (!$controller) {
			throw new RuntimeException('Error controller required before creating routes', 500);
		}

		return static::$routes[] = Route::create($method, $uri, $callback, $middleware[0]);
	}

	public static function get(string $uri, string|array|callable $callback): Route {
		return static::route('GET', $uri, $callback);
	}

	public static function put(string $uri, string|array|callable $callback): Route {
		return static::route('PUT', $uri, $callback);
	}

	public static function post(string $uri, string|array|callable $callback): Route {
		return static::route('POST', $uri, $callback);
	}

	public static function delete(string $uri, string|array|callable $callback): Route {
		return static::route('DELETE', $uri, $callback);
	}

	public static function all(string $uri, string|array|callable $callback): Route {
		return static::route('ALL', $uri, $callback);
	}

	public static function group(string $prefix, Callable $group_callable, array $group_middleware = []): void {
		static::$uri .= $prefix;

		if ($group_middleware !== []) {
			$cache_middleware = static::$middleware;
			static::setMiddleware($group_middleware[0] ?? '', $group_middleware[1] ?? '');
		}

		call_user_func($group_callable);
		static::$uri = rtrim(static::$uri, $prefix);

		if ($group_middleware !== []) {
			static::setMiddleware($cache_middleware[0] ?? '', $cache_middleware[1] ?? '');
			unset($cache_middleware);
		}
	}

	protected static function getMethod(): string {
		return $_SERVER['REQUEST_METHOD'];
	}

	protected static function getURI(): string {
		$parts = explode('?', $_SERVER['REQUEST_URI']);
		return array_shift($parts);
	}

	/** @return Route[] */
	public static function getRoutes(): array {
		return static::$routes;
	}

	public static function run(): void {
		try {
			$routes = array_filter(static::$routes, static function(Route $route) {
				return ($route->getMethod() === static::getMethod() || $route->getMethod() === 'ALL');
			});

			/** @var Route[] $matched_routes */
			$matched_routes = [];

			foreach ($routes as $route) {
				$route_uri = $route->getRegexURI();

				$uri_match_count = preg_match_all(sprintf('/%s/', $route_uri), static::getURI(), $uri_matches);

				if ($uri_match_count > 0) {
					foreach ($uri_matches as $uri_match) {
						if (($uri_match[0] ?? '') === static::getURI()) {
							$matched_routes[] = $route;
						}
					}
				}
			}

			unset($route);

			/* Fetch last matched route */
			/** @var Route $route */
			$route = end($matched_routes);

			if (empty($matched_routes) && is_bool($route) && !$route) {
				throw new HttpException(sprintf(
					"The requested URI '%s' doesn't exist",
					static::getURI(),
				), 404);
			}

			$request = Request::createFromGlobals();

			if (!isset($response)) {
				$callback = $route->getCallback();

				/* Run middleware */
				$middleware_result = call_user_func(static::$middleware);

				if (!$middleware_result) {
					return;
				}

				$arguments = $route->getParameters(static::getURI());
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
			}

			static::handleOutput($route, $request, $response);
		} catch (HttpException|RuntimeException $ex) {
			$response = call_user_func_array(static::$controller, [ $ex ]);

			static::handleOutput(null, null, $response, true);
		}
	}

	/**
	 * @throws RuntimeException
	 */
	protected static function handleOutput(?Route $route = null, ?Request $request = null, ?object $response = null, bool $is_error = false): void {
		if (!$response instanceof Response) {
			throw new RuntimeException(sprintf(
				"Response has to be instance of class '%s'",
				Response::class,
			), 500);
		}

		/* Process response from controller */
		http_response_code($response->getStatus());

		if (self::$skip_lifecycle && !$is_error) {
			static::preHeaders($route, $request, $response);
		}

		foreach ($response->getHeaders() as $header => $value) {
			header(sprintf('%s: %s', $header, $value), true, $response->getStatus());
		}

		if (self::$skip_lifecycle && !$is_error) {
			static::postHeaders($route, $request, $response);
			static::preWrite($route, $request, $response);
		}

		echo $response->getBody();

		if (self::$skip_lifecycle && !$is_error) {
			static::postWrite($route, $request, $response);
		}
	}

	/** Version handling */

	#[ArrayShape([
		'major' => 'int',
		'minor' => 'int',
		'patch' => 'int',
	])]
	public static function getSemVer(): array {
		$semantic_version = explode('.', static::VERSION);

		return [
			'major' => (int) $semantic_version[0] ?? 0,
			'minor' => (int) $semantic_version[1] ?? 0,
			'patch' => (int) $semantic_version[2] ?? 0,
		];
	}

	public static function getMajor():int {
		return static::getSemVer()['major'];
	}

	public static function getMinor(): int {
		return static::getSemVer()['minor'];
	}

	public static function getPatch(): int {
		return static::getSemVer()['patch'];
	}

	public static function getVersion(): string {
		return sprintf(
			'v%d.%d.%d',
			static::getMajor(),
			static::getMinor(),
			static::getPatch(),
		);
	}

	/** Lifecycle events */

	public static function setSkipLifecycle(bool $skip_lifecycle): void {
		self::$skip_lifecycle = $skip_lifecycle;
	}

	/**
	 * Handle Route, Request & Response before output
	 */
	protected static function preWrite(?Route $route = null, ?Request $request = null, ?Response $response = null): void {}

	/**
	 * Handle Route, Request & Response after output
	 */
	protected static function postWrite(?Route $route = null, ?Request $request = null, ?Response $response = null): void {}

	/**
	 * Handle Route, Request & Response before handling headers
	 */
	protected static function preHeaders(?Route $route = null, ?Request $request = null, ?Response $response = null): void {}

	/**
	 * Handle Route, Request & Response after handling headers
	 */
	protected static function postHeaders(?Route $route = null, ?Request $request = null, ?Response $response = null): void {}

}
