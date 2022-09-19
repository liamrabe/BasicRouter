<?php
namespace LiamRabe\BasicRouter\Trait;

use JetBrains\PhpStorm\ArrayShape;

trait SemanticVersionTrait {

	#[ArrayShape([
		'major' => 'int',
		'minor' => 'int',
		'patch' => 'int',
	])]
	public function getSemVer(): array {
		$semantic_version = explode('.', static::VERSION);

		return [
			'major' => (int) $semantic_version[0] ?? 0,
			'minor' => (int) $semantic_version[1] ?? 0,
			'patch' => (int) $semantic_version[2] ?? 0,
		];
	}

	public function getMajor():int {
		return $this->getSemVer()['major'];
	}

	public function getMinor(): int {
		return $this->getSemVer()['minor'];
	}

	public function getPatch(): int {
		return $this->getSemVer()['patch'];
	}

	public function getVersion(): string {
		return sprintf(
			'v%d.%d.%d',
			$this->getMajor(),
			$this->getMinor(),
			$this->getPatch(),
		);
	}

}
