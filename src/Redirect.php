<?php
namespace LiamRabe\BasicRouter;

use RuntimeException;

class Redirect {

	private function __construct(
		private readonly string $uri,
		private readonly string $target,
		private readonly int $status,
	) {}

	public function getCode(): int {
		return $this->status;
	}

	public function getURI(): string {
		return $this->uri;
	}

	public function getTarget(): string {
		return $this->target;
	}

	/** Static methods */

	/**
	 * @param string $uri
	 * @param string $target
	 * @param int $status
	 * @return static
	 */
	public static function create(string $uri, string $target, int $status): self {
		if ($uri === $target) {
			throw new RuntimeException("You can't create a redirect a URL to itself");
		}

		return new self($uri, $target, $status);
	}

}
