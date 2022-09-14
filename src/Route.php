<?php
namespace LiamRabe\BasicRouter;

use LiamRabe\BasicRouter\DataCollection\Parameters;

class Route {

	protected const ARGUMENT_SEARCH = '/{(\w+):([\w\-_+\[\].\\\]+)}/';

	protected function __construct(
		protected string $method,
		protected string $uri,
		protected string|array|object $callback,
		protected string $middleware
	) {}

	public function getMethod(): string {
		return $this->method;
	}

	public function getURI(): string {
		return $this->uri;
	}

	public function getCallback(): string|array|Callable {
		return $this->callback;
	}

	public function getMiddleware(): object {
		return new $this->middleware();
}

	public function getArguments(bool $raw = false): array|Parameters {
		$regex = Regex::all(self::ARGUMENT_SEARCH, $this->getURI());
		$matches = [];

		if ($regex->getCount() > 0) {
			foreach ($regex->getGroups() as $group) {
				if (($group[0] ?? null) !== null && ($group[1] ?? null) !== null) {
					$matches[$group[0]] = $group[1];
				}
			}
		}

		return ((!$raw) ? new Parameters($matches) : $matches);
	}

	public function getRegexURI(): string {
		$arguments = $this->getArguments()->all();
		$uri = str_replace('/', '\/', $this->getURI());

		foreach ($arguments as $name => $pattern) {
			$uri = str_replace(sprintf(
				'{%s:%s}', $name, $pattern
			), sprintf('(%s)', $pattern), $uri);
		}

		return $uri;
	}

	public function getParameters(string $uri): Parameters {
		$uri_pattern = sprintf('/%s/', $this->getRegexURI());
		$uri_matches = [];

		preg_match_all($uri_pattern, $uri, $uri_matches);
		array_shift($uri_matches);

		$arguments = [];
		foreach (array_keys($this->getArguments(true)) as $index => $argument_name) {
			$arguments[$argument_name] = $uri_matches[$index][0];
		}

		return new Parameters($arguments);
	}

	/* Static methods */

	public static function create(string $method, string $uri, string|array|Callable $callback, string $middleware): self {
		return new self($method, $uri, $callback, $middleware);
	}

}
