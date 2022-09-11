<?php
namespace Liam\BasicRouter\HTTP\Entity;

class Cookie extends BaseEntity {

	public static function assemble(): static {
		return new static($_COOKIE);
	}

}
