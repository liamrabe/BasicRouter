<?php
namespace Liam\BasicRouter\DataCollection;

class Parameters extends BaseCollection {

	public function __construct(
		protected array $parameters
	) {}

	public function get(string $key, mixed $default = null): ?string {
		return $this->parameters[$key] ?? $default;
	}

	public function all(): array {
		return $this->parameters;
	}

}
