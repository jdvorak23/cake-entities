<?php

namespace Cesys\CakeEntities\Model\Find;

class FindConditions
{
	public ?FindConditions $or = null;

	/**
	 * @var array<string, mixed>
	 */
	public array $keyConditions = [];

	/**
	 * @var string[]
	 */
	public array $stringConditions = [];

	public static function create(array $conditions = []): self
	{
		$instance = new self();
		foreach ($conditions as $key => $condition) {
			if (is_int($key)) {
				// Klíč je číslo, vnořená se ve find nepoužívá
				$instance->stringConditions[] = $condition;
				continue;
			}
			$lowerKey = strtolower($key);
			if ($lowerKey === 'or') {
				$instance->or = self::create($condition);
			} else {
				// And ani not find nepoužívá
				$instance->keyConditions[$key] = $condition;
			}
		}
		return $instance;
	}

	public function toArray(): array
	{
		$params = [];
		foreach ($this->keyConditions as $key => $condition) {
			$params[$key] = $condition;
		}
		if ($this->or && ! $this->or->isEmpty()) {
			$params['OR'] = $this->or->toArray();
		}

		foreach ($this->stringConditions as $stringCondition) {
			$params[] = $stringCondition;
		}

		return $params;
	}


	public function clear(): void
	{
		$this->keyConditions = [];
		$this->stringConditions = [];
		$this->or && $this->or->clear();
	}

	public function getOr(): self
	{
		return $this->or ??= new static();
	}

	public function addOrCondition(string $column, $value)
	{
		$or = $this->getOr();
		if ( ! isset($or->keyConditions[$column])) {
			$or->keyConditions[$column] = [];
		}
		if ( ! in_array($value, $or->keyConditions[$column], true)) { // todo strict?
			$or->keyConditions[$column][] = $value;
		}
	}

	public function isEmpty(): bool
	{
		return empty($this->keyConditions)
			&& empty($this->stringConditions)
			&& ($this->or === null || $this->or->isEmpty());
	}
}