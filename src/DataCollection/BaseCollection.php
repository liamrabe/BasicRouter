<?php
namespace Liam\BasicRouter\DataCollection;

abstract class BaseCollection {

	abstract public function get(string $key, mixed $default): ?string;
	abstract public function all(): array;

}
