<?php

namespace Cesys\CakeEntities\Model\Find;

use Cesys\Utils\Reflection;

class Params
{
	public ?Conditions $conditions = null;

	public int $recursive = -1;

	public array $fields = [];
	public ?array $order = null;

	public array $joins = [];
	public ?array $group = null;
	public ?int $limit = null;
	public ?int $page = null;

	public ?int $offset = null;

	/**
	 * @var bool|string true, false, 'before', 'after'
	 */
	public $callbacks = true;

	public static function create(array $params): self
	{
		$instance = new self();
		foreach (Reflection::getReflectionPropertiesOfClass(static::class) as $property) {
			if (array_key_exists($property->getName(), $params)) {
				$value = $params[$property->getName()];
				if ($value === null && $property->getType()->allowsNull()) {
					$property->setValue($instance, null);
					continue;
				}
				if ($property->getType()->getName() === Conditions::class && is_array($value)) {
					$property->setValue($instance, Conditions::create($value));
					continue;
				}
				if ($property->getType()->getName() === 'array') {
					if ( ! $property->getType()->allowsNull() && $value === null) {
						$value = [];
					} elseif ( ! is_array($value)) {
						$value = [$value];
					}
				}
				$property->setValue($instance, $value);
			}
		}
		return $instance;
	}

	public function toArray(): array
	{
		$params = [];
		foreach (Reflection::getReflectionPropertiesOfClass(static::class) as $property) {
			$value = $property->getValue($this);
			if ($value instanceof Conditions) {
				$value = $value->toArray();
			}
			$params[$property->getName()] = $value;
		}
		return $params;
	}

	public function clear(): void
	{
		$this->conditions = null;
		$this->setRecursive(-1)
			->setFields()
			->setOrder()
			->setJoins()
			->setGroup()
			->setLimit()
			->setPage()
			->setOffset()
			->setCallbacks();
	}


	public function getConditions(): Conditions
	{
		return $this->conditions ??= new Conditions();
	}

	public function isEqualTo(self $params): bool
	{
		foreach (Reflection::getReflectionPropertiesOfClass(static::class) as $property) {
			if ($property->getName() === 'conditions') {
				continue;
			}
			if ($property->getName() === 'fields') {
				// Dělá problémy, fields defaultne nastavene takze ok
				continue;
			}
			// ??&& empty($params->{$property->getName()}[0]
			if (empty($this->{$property->getName()}) && empty($params->{$property->getName()})) {
				continue;
			}
			if ($property->getValue($this) !== $property->getValue($params)) {
				return false;
			}
		}

		return true;
	}


	public function setRecursive(int $recursive = -1): self
	{
		$this->recursive = $recursive;
		return $this;
	}


	public function setFields(array $fields = []): self
	{
		$this->fields = $fields;
		return $this;
	}


	public function setOrder(?array $order = null): self
	{
		$this->order = $order;
		return $this;
	}


	public function setJoins(array $joins = []): self
	{
		$this->joins = $joins;
		return $this;
	}


	public function setGroup(?array $group = null): self
	{
		$this->group = $group;
		return $this;
	}


	public function setLimit(?int $limit = null): self
	{
		$this->limit = $limit;
		return $this;
	}


	public function setPage(?int $page = null): self
	{
		$this->page = $page;
		return $this;
	}


	public function setOffset(?int $offset = null): self
	{
		$this->offset = $offset;
		return $this;
	}


	/**
	 * @param bool|string $callbacks true, false, 'before', 'after'
	 * @return void
	 */
	public function setCallbacks($callbacks = true): self
	{
		$this->callbacks = $callbacks;
		return $this;
	}


}