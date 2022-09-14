<?php
namespace LiamRabe\BasicRouter\HTTP\Entity;

class Post extends BaseEntity {

	public static function assemble(): static {
		return new static($_POST);
	}

}
