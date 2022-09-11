<?php
namespace Liam\BasicRouter;

class Regex {

	private function __construct(
		protected int $count,
		protected array $groups
	) {}

	public function getCount(): int {
		return $this->count;
	}

	public function getGroups(): array {
		return $this->groups;
	}

	/** Static methods */

	protected static function normalize(array $match_groups): array {
		$groups = [];

		array_shift($match_groups);

		/** Normalize array */
		foreach ($match_groups as $group_items) {
			foreach ($group_items as $ix => $group_item) {
				$groups[$ix][] = $group_item;
			}
		}

		return $groups;
	}

	public static function all(string $pattern, string $subject): self {
		$match_count = preg_match_all($pattern, $subject, $match_groups);
		return new self($match_count, self::normalize($match_groups));
	}

}
