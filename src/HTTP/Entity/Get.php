<?php
namespace LiamRabe\BasicRouter\HTTP\Entity;

class Get extends BaseEntity {

	public static function assemble(): static {
		return new static($_GET);
	}

}
