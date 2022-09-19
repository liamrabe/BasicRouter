<?php
namespace LiamRabe\BasicRouter\Route;

use LiamRabe\BasicRouter\DataCollection\Parameters;

interface IRoute {

	public function __construct(
		string $method,
		string $uri,
		array $callback,
		array $middlewares
	);

	public function getURI(): string;

	public function getMethod(): string;

	public function getCallback(): string|array|Callable;

	/**
	 * @return object[]
	 */
	public function getMiddlewares(): array;

	/**
	 * Return an instance of Parameters with data from the URL, ex GET-parameters should be returned from here
	 *
	 * @see Route::getParameters
	 */
	public function getParameters(string $uri): Parameters;

	/**
	 * Check if current route is a match to the current request uri
	 */
	public function isMatch(string $uri): bool;

}
