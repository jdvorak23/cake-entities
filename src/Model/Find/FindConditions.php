<?php

namespace Cesys\CakeEntities\Model\Find;

class FindConditions
{
	private FindOrConditions $or;

	/**
	 * @var array<int, string>
	 */
	public array $stringConditions = [];

	/**
	 * @var FindOrConditions[]
	 */
	private array $orConditionsHistory = [];

	public function __construct()
	{
		$this->or = new FindOrConditions();
	}

	/**
	 * @param array $conditions
	 * @return static
	 */
	public static function create(array $conditions = [])
	{
		$instance = new static();
		foreach ($conditions as $key => $condition) {
			if (is_int($key)) {
				// Klíč je číslo, vnořená se ve find nepoužívá
				if (is_string($condition) && $condition !== '') {
					$instance->stringConditions[] = $condition;
				}
				continue;
			}
			$lowerKey = strtolower($key);
			if ($lowerKey === 'or') {
				if (is_array($condition) && $condition) {
					foreach ($condition as $column => $values) {
						foreach ($values as $value) {
							$instance->or->addCondition($column, $value);
						}
					}
				}
			}
		}
		return $instance;
	}

	public function toArray(): array
	{
		$conditions = [];
		if ( ! $this->or->isEmpty()) {
			$conditions['OR'] = $this->or->toArray();
		}

		foreach ($this->stringConditions as $stringCondition) {
			$conditions[] = $stringCondition;
		}

		return $conditions;
	}


	public function clear(): void
	{
		$this->stringConditions = [];
		$this->or->clear();
	}

	public function getOr(): FindOrConditions
	{
		return $this->or;
	}

	public function addOrCondition(string $column, $value)
	{
		$this->or->addCondition($column, $value);
	}


	/**
	 * @param string $column
	 * @param string $value
	 * @return bool Jestli je index $column odebrán (prázdný)
	 */
	public function removeOrCondition(string $column, string $value): bool
	{
		return $this->or->removeCondition($column, $value);
	}

	public function isEmpty(): bool
	{
		return empty($this->stringConditions)
			&& $this->or->isEmpty();
	}

	public function hasStringConditions(): bool
	{
		return ! empty($this->stringConditions);
	}

	/**
	 * @return bool true -> bylo uloženo = poprvé, false => tyhle už byly
	 */
	public function stampOrConditions(): bool
	{
		foreach ($this->orConditionsHistory as $orConditions) {
			if ($this->or->isEqualTo($orConditions)) {
				return false;
			}
		}
		$this->orConditionsHistory[] = clone $this->or;
		return true;
	}

	public function getLastOrConditionsHistory(): FindOrConditions
	{
		$index = array_key_last($this->orConditionsHistory);
		return $this->orConditionsHistory[$index];
	}
}