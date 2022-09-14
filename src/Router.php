<?php
namespace LiamRabe\BasicRouter;

use LiamRabe\BasicRouter\DataCollection\Response;
use LiamRabe\BasicRouter\Exception\HttpException;
use LiamRabe\BasicRouter\DataCollection\Request;
use JetBrains\PhpStorm\ArrayShape;
use InvalidArgumentException;
use RuntimeException;

class Router {

	protected const VERSION = '2.0.0';

	protected static array $controller;
	protected static array $middleware;

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

		self::$middleware = [
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

		self::$controller = [
			$controller,
			$method,
		];
	}

	public static function setExposeRouter(bool $expose): void {
		self::$expose_router = $expose;
	}

	public static function getExposeRouter(): bool {
		return self::$expose_router;
	}

	public static function redirect(string $uri, string $target, int $code = 301): void {
		if (self::getURI() === $uri) {
			header(sprintf('Location: %s', $target), true,  $code);
			exit;
		}
	}

	/** @throws RuntimeException */
	protected static function route(string $method, string $uri, string|array|callable $callback): Route {
		if (self::$uri !== '') {
			$uri = sprintf('%s/%s', self::$uri, ltrim($uri, '/'));
		}

		$middleware = self::$middleware ?? null;
		if (!$middleware) {
			throw new RuntimeException('Middleware required before creating routes', 500);
		}

		$controller = self::$controller ?? null;
		if (!$controller) {
			throw new RuntimeException('Error controller required before creating routes', 500);
		}

		return self::$routes[] = Route::create($method, $uri, $callback, $middleware[0]);
	}

	public static function get(string $uri, string|array|callable $callback): Route {
		return self::route('GET', $uri, $callback);
	}

	public static function put(string $uri, string|array|callable $callback): Route {
		return self::route('PUT', $uri, $callback);
	}

	public static function post(string $uri, string|array|callable $callback): Route {
		return self::route('POST', $uri, $callback);
	}

	public static function delete(string $uri, string|array|callable $callback): Route {
		return self::route('DELETE', $uri, $callback);
	}

	public static function all(string $uri, string|array|callable $callback): Route {
		return self::route('ALL', $uri, $callback);
	}

	public static function group(string $prefix, Callable $group_callable, array $group_middleware = []): void {
		self::$uri .= $prefix;

		if ($group_middleware !== []) {
			$cache_middleware = self::$middleware;
			self::setMiddleware($group_middleware[0] ?? '', $group_middleware[1] ?? '');
		}

		call_user_func($group_callable);
		self::$uri = rtrim(self::$uri, $prefix);

		if ($group_middleware !== []) {
			self::setMiddleware($cache_middleware[0] ?? '', $cache_middleware[1] ?? '');
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
		return self::$routes;
	}

	public static function run(): void {
		try {
			$routes = array_filter(self::$routes, static function(Route $route) {
				return ($route->getMethod() === self::getMethod() || $route->getMethod() === 'ALL');
			});

			/** @var Route[] $matched_routes */
			$matched_routes = [];

			foreach ($routes as $route) {
				$route_uri = $route->getRegexURI();

				$uri_match_count = preg_match_all(sprintf('/%s/', $route_uri), self::getURI(), $uri_matches);

				if ($uri_match_count > 0) {
					foreach ($uri_matches as $uri_match) {
						if (($uri_match[0] ?? '') === self::getURI()) {
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
					self::getURI(),
				), 404);
			}

			if (!isset($response)) {
				$callback = $route->getCallback();

				/* Run middleware */
				$middleware_result = call_user_func(self::$middleware);

				if (!$middleware_result) {
					return;
				}

				$arguments = $route->getParameters(self::getURI());

				$request = Request::createFromGlobals($arguments);
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

			self::handleOutput($response);
		} catch (HttpException|RuntimeException $ex) {
			$response = call_user_func_array(self::$controller, [
				$ex
			]);

			self::handleOutput($response);
		}
	}

	/**
	 * @throws RuntimeException
	 */
	protected static function handleOutput(object $response): void {
		if (!$response instanceof Response) {
			throw new RuntimeException(sprintf(
				"Response has to be instance of class '%s'",
				Response::class,
			), 500);
		}

		/* Process response from controller */
		http_response_code($response->getStatus());

		foreach ($response->getHeaders() as $header => $value) {
			header(sprintf('%s: %s', $header, $value), true, $response->getStatus());
		}

		echo $response->getBody();
	}

	/** Version handling */

	#[ArrayShape([
		'major' => 'int',
		'minor' => 'int',
		'patch' => 'int',
	])]
	public static function getSemVer(): array {
		$semantic_version = explode('.', self::VERSION);

		return [
			'major' => (int) $semantic_version[0] ?? 0,
			'minor' => (int) $semantic_version[1] ?? 0,
			'patch' => (int) $semantic_version[2] ?? 0,
		];
	}

	public static function getMajor():int {
		return self::getSemVer()['major'];
	}

	public static function getMinor(): int {
		return self::getSemVer()['minor'];
	}

	public static function getPatch(): int {
		return self::getSemVer()['patch'];
	}

	public static function getVersion(): string {
		return sprintf(
			'v%d.%d.%d',
			self::getMajor(),
			self::getMinor(),
			self::getPatch(),
		);
	}

}
