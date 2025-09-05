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

	public static function create(array $conditions = []): self
	{
		$instance = new self();
		foreach ($conditions as $key => $condition) {
			if (is_int($key)) {
				// Klíč je číslo, tj. buď string nebo vnořená
				if (is_array($condition)) {
					if ($condition) {
						$instance->innerConditions[] = self::create($condition);
					}
				} else {
					if (is_string($condition) && $condition !== '') {
						$instance->stringConditions[] = $condition;
					}
				}
				continue;
			}
			$lowerKey = strtolower($key);
			if ($lowerKey === 'or') {
				if (is_array($condition) && $condition) {
					$instance->or = static::create($condition);
				}
			} else if ($lowerKey === 'and') {
				if (is_array($condition) && $condition) {
					$instance->and = self::create($condition);
				}
			} else if ($lowerKey === 'not') {
				if (is_array($condition) && $condition) {
					$instance->not = self::create($condition);
				}
			} else {
				// todo $key??
				if ($condition !== '') {
					$instance->keyConditions[$key] = $condition;
				}
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
		if ($this->and && ! $this->and->isEmpty()) {
			$params['AND'] = $this->and->toArray();
		}
		if ($this->not && !$this->not->isEmpty()) {
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
			&& empty($this->innerConditions) // todo
			&& ($this->or === null || $this->or->isEmpty())
			&& ($this->and === null || $this->and->isEmpty())
			&& ($this->not === null || $this->not->isEmpty());
	}

	public static function isEqual(self $conditions1, self $conditions2): bool
	{
		if ($conditions1 === $conditions2) {
			return true;
		}
		foreach (['or', 'and', 'not'] as $conditionType) {
			if ($conditions1->{$conditionType} === null || $conditions1->{$conditionType}->isEmpty()) {
				// 1 má prázdné {$conditionType}
				if ($conditions2->{$conditionType} !== null && ! $conditions2->{$conditionType}->isEmpty()) {
					// 2 nemá prázdné {$conditionType}
					return false;
				}
			} elseif ($conditions2->{$conditionType} === null || $conditions2->{$conditionType}->isEmpty()) {
				// 1 nemá prázdné {$conditionType}
				// 2 má prázdné {$conditionType}
				return false;
			} elseif ( ! static::isEqual($conditions1->{$conditionType}, $conditions2->{$conditionType})) {
				return false;
			}
		}
		if (
			count($conditions1->keyConditions) !== count($conditions2->keyConditions)
			|| array_diff_key($conditions1->keyConditions, $conditions2->keyConditions)
		) {
			return false;
		}
		foreach ($conditions1->keyConditions as $key => $condition) {
			if (gettype($condition) !== gettype($conditions2->keyConditions[$key])) {
				return false;
			}
			if (is_array($condition)) {
				if (
					count($condition) !== count($conditions2->keyConditions[$key])
					|| array_diff($condition, $conditions2->keyConditions[$key])
				) {
					return false;
				}
			} elseif ($condition !== $conditions2->keyConditions[$key]) {
				return false;
			}
		}
		if (
			count($conditions1->stringConditions) !== count($conditions2->stringConditions)
			|| array_diff($conditions1->stringConditions, $conditions2->stringConditions)
		) {
			return false;
		}
		if (count($conditions1->innerConditions) !== count($conditions2->innerConditions)) {
			return false;
		}
		$innerConditions2 = $conditions2->innerConditions;
		foreach ($conditions1->innerConditions as $condition1) {
			$found = false;
			foreach ($innerConditions2 as $key => $condition2) {
				if (static::isEqual($condition1, $condition2)) {
					$found = true;
					unset($innerConditions2[$key]);
					break;
				}
			}
			if ( ! $found) {
				return false;
			}
		}
		return true;
	}
}