<?php
namespace Liam\BasicRouter\HTTP\Entity;

class Get extends BaseEntity {

	public static function assemble(): static {
		return new static($_GET);
	}

}
