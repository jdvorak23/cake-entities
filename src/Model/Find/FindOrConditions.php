<?php

namespace Cesys\CakeEntities\Model\Find;

class FindOrConditions
{
	/**
	 * @var string[][]
	 */
	private array $columnConditions = [];


	public function toArray(): array
	{
		$conditions = [];
		foreach ($this->columnConditions as $column => $values) {
			foreach (array_keys($values) as $value) {
				$conditions[$column][] = $value;
			}
		}
		return $conditions;
	}


	public function clear(): void
	{
		$this->columnConditions = [];
	}


	public function addCondition(string $column, string $value): void
	{
		$this->columnConditions[$column][$value] = true;
	}

	/**
	 * @param string $column
	 * @param string $value
	 * @return bool Jestli je index $column odebrán (prázdný)
	 */
	public function removeCondition(string $column, string $value): bool
	{
		unset($this->columnConditions[$column][$value]);
		if (empty($this->columnConditions[$column])) {
			unset($this->columnConditions[$column]);
			return true;
		}
		return false;
	}

	public function isEmpty(): bool
	{
		return empty($this->columnConditions);
	}


	public function isEqualTo(self $conditions): bool
	{
		if ($this === $conditions) {
			return true;
		}
		if (
			count($this->columnConditions) !== count($conditions->columnConditions)
			|| array_diff_key($this->columnConditions, $conditions->columnConditions)
		) {
			return false;
		}

		foreach ($this->columnConditions as $column => $values) {
			if (
				count($this->columnConditions[$column]) !== count($conditions->columnConditions[$column])
				|| array_diff_key($this->columnConditions[$column], $conditions->columnConditions[$column])
			) {
				return false;
			}
		}

		return true;
	}
}