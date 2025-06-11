<?php

namespace Cesys\CakeEntities\Model\Find;

class Conditions
{
	public ?Conditions $or = null;

	public ?Conditions $and = null;

	public ?Conditions $not = null;

	/**
	 * @var array<string, mixed>
	 */
	public array $keyConditions = [];

	/**
	 * @var string[]
	 */
	public array $stringConditions = [];

	/**
	 * @var Conditions[]
	 */
	public array $innerConditions = [];

	public static function create(array $conditions): self
	{
		$instance = new self();
		foreach ($conditions as $key => $condition) {
			if (is_int($key)) {
				// Klíč je číslo, tj. buď string nebo vnořená
				if (is_array($condition)) {
					$instance->innerConditions[] = self::create($condition);
				} else {
					$instance->stringConditions[] = $condition;
				}
				continue;
			}
			$lowerKey = strtolower($key);
			if ($lowerKey === 'or') {
				$instance->or = self::create($condition);
			} else if ($lowerKey === 'and') {
				$instance->and = self::create($condition);
			} else if ($lowerKey === 'not') {
				$instance->not = self::create($condition);
			} else {
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
		if ($this->or) {
			$params['OR'] = $this->or->toArray();
		}
		if ($this->and) {
			$params['AND'] = $this->and->toArray();
		}
		if ($this->not) {
			$params['NOT'] = $this->not->toArray();
		}

		foreach ($this->stringConditions as $stringCondition) {
			$params[] = $stringCondition;
		}

		foreach ($this->innerConditions as $condition) {
			$params[] = $condition->toArray();
		}
		return $params;
	}

	public function addOrCondition(string $column, $value)
	{
		if ( ! isset($this->or)) {
			$this->or = new Conditions();
		}
		if ( ! isset($this->or->keyConditions[$column])) {
			$this->or->keyConditions[$column] = [];
		}
		if ( ! in_array($value, $this->or->keyConditions[$column], true)) {
			$this->or->keyConditions[$column][] = $value;
		}
	}

	public function isEmpty(): bool
	{
		return empty($this->keyConditions)
			&& empty($this->stringConditions)
			&& empty($this->innerConditions)
			&& ($this->or === null || $this->or->isEmpty())
			&& ($this->and === null || $this->and->isEmpty())
			&& ($this->not === null || $this->not->isEmpty());
	}
}