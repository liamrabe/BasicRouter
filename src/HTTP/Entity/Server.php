<?php
namespace Liam\BasicRouter\HTTP\Entity;

class Server extends BaseEntity {

	public static function assemble(): static {
		return new static($_SERVER);
	}

}
