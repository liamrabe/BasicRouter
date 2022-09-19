<?php
namespace LiamRabe\BasicRouter\DataCollection;

use LiamRabe\BasicRouter\Route\AbstractRoute;

class RouteCollection {

	/** @var AbstractRoute[] */
	private static array $routes = [];

	public static function addRoute(AbstractRoute $route): AbstractRoute {
		return self::$routes[] = $route;
	}

	/** @return AbstractRoute[] */
	public static function getRoutes(string $method, string $uri): array {
		return array_filter(self::$routes, static function(AbstractRoute $route) use ($method, $uri) {
			return $method === $route->getMethod() && $route->isMatch($uri);
		});
	}

}
