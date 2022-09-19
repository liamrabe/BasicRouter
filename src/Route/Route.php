<?php
namespace LiamRabe\BasicRouter\Route;

use LiamRabe\BasicRouter\DataCollection\Parameters;
use LiamRabe\BasicRouter\Regex;

final class Route extends AbstractRoute {

	protected const ARGUMENT_SEARCH = '/{(\w+):([\w\-_+\[\].\\\]+)}/';

	protected array $arguments = [];

	public function __construct(
		private readonly string $method,
		private readonly string $uri,
		private readonly array $callback,
		private readonly array $middlewares
	) {
		$this->arguments = $this->getArguments();
	}

	private function getArguments(): array {
		$regex = Regex::all(self::ARGUMENT_SEARCH, $this->uri);
		$matches = [];

		if ($regex->getCount() > 0) {
			foreach ($regex->getGroups() as $group) {
				if (($group[0] ?? null) !== null && ($group[1] ?? null) !== null) {
					$matches[$group[0]] = $group[1];
				}
			}
		}

		return $matches;
	}

	public function getParameters(string $uri): Parameters {
		$uri_pattern = sprintf('/%s/', $this->getPattern());
		$uri_matches = [];

		preg_match_all($uri_pattern, $uri, $uri_matches);
		array_shift($uri_matches);

		$arguments = [];
		foreach (array_keys($this->getArguments()) as $index => $argument_name) {
			$arguments[$argument_name] = $uri_matches[$index][0];
		}

		return new Parameters($arguments);
	}

	public function getPattern(): string {
		$arguments = $this->getArguments();
		$uri = str_replace('/', '\/', $this->uri);

		foreach ($arguments as $name => $pattern) {
			$uri = str_replace(sprintf(
				'{%s:%s}', $name, $pattern
			), sprintf('(%s)', $pattern), $uri);
		}

		return $uri;
	}

	public function getURI(): string {
		return $this->uri;
	}

	public function isMatch(string $uri): bool {
		$has_match = false;

		preg_match_all(sprintf(
			'/%s/', $this->getPattern(),
		), $uri, $uri_matches);

		if (!$uri_matches) {
			$uri_matches = [];
		}

		foreach ($uri_matches as $uri_match) {
			if (($uri_match[0] ?? '') === $uri) {
				$has_match = true;
				break;
			}
		}

		return $has_match;
	}

	public function getMethod(): string {
		return $this->method;
	}

	public function getCallback(): array {
		return $this->callback;
	}

	/**
	 * @return object[]
	 */
	public function getMiddlewares(): array {
		return array_reduce($this->middlewares, static function(array $carry, string $middleware) {
			$carry[] = new $middleware();
			return $carry;
		}, []);
	}

}
