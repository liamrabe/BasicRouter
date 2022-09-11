<?php
namespace Liam\BasicRouter\HTTP\Entity;

abstract class BaseEntity {

	protected function __construct(protected array $method_data) {}

	public function getData(): array {
		return $this->method_data;
	}

	public function __get($array = []): array {
		return $this->method_data;
	}

	public function get(string $row): mixed {
		return $this->method_data[$row] ?? null;
	}

	/* Static methods */

	abstract static public function assemble(): static;

	public static function has(string $name): bool {
		return (static::assemble()->getData()[$name] ?? null) !== null;
	}

}
