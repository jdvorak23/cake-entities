<?php

namespace Cesys\CakeEntities\Model\Find;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Cesys\CakeEntities\Model\Entities\ColumnProperty;
use Cesys\CakeEntities\Model\GetModelTrait;
use Cesys\Utils\Arrays;

class EntityCache
{
	use GetModelTrait;
	public Contains $contains;

	private /*todo*/ $model;
	private string $primaryKey;

	private array $cache = [];

	private array $onAdd = [];

	private Stash $stash;

	public function __construct(Contains $contains, Stash $stash)
	{
		$this->contains = $contains;
		$this->model = $this->getModel($contains->modelClass);
		$this->primaryKey = $this->model->primaryKey;
		$this->createIndex($this->primaryKey);
		$this->stash = $stash;
		/*if ($stashCache && $contains === $stashCache->contains) {
			bdump("ff");
			$this->cache[$this->primaryKey] = $stashCache->cache[$this->primaryKey];
		}*/
	}


	public function add(CakeEntity $entity)
	{
		foreach (array_keys($this->cache) as $column) {
			$this->indexEntity($entity, $column);
		}
		$this->stash->add($entity);

	}

	public function addIndex(string $column): bool
	{
		if ( ! isset($this->cache[$column])) {
			$this->createIndex($column);
			return true;
		}

		return false;
	}

	public function startIndexValue(string $column, $value): bool
	{
		if ( ! isset($this->cache[$column][$value])) {
			$this->cache[$column][$value] = [];
			return true;
		}

		return false;
	}

	public function indexEntities(string $column)
	{
		if ($this->primaryKey === $column) {
			return;
		}
		foreach ($this->getEntitiesByPrimary() as $entity) {
			$this->indexEntity($entity, $column);
		}
	}

	public function getEntity($value, ?string $column = null): ?CakeEntity
	{
		if ($column === null) {
			$column = $this->primaryKey;
		}
		if (empty($this->cache[$column][$value])) {
			return null;
		}

		return current($this->cache[$column][$value]);
	}

	/**
	 * @return CakeEntity[]
	 */
	public function getEntities($value, ?string $column = null): array
	{
		if ($column === null) {
			$column = $this->primaryKey;
		}
		return $this->cache[$column][$value] ?? [];
	}

	public function getEntitiesByPrimary(): array
	{
		// todo blbe orderasi
		return array_intersect_key($this->stash->getCache(), $this->cache[$this->primaryKey]);
	}

	public function getCache(?string $column = null): ?array
	{
		if ($column === null) {
			$column = $this->primaryKey;
		}
		return $this->cache[$column] ?? null;
	}


	public  function setOnAdd(string $column, $value, bool $onlyFirst, callable $onAdd)
	{
		if ($onlyFirst) {
			if ( ! empty($this->cache[$column][$value])) {
				$onAdd($this->cache[$column][$value][array_key_first($this->cache[$column][$value])]);
			} else {
				$this->onAdd['first'][$column][$value][] = $onAdd;
			}
			return;
		}

		$this->onAdd['all'][$column][$value][] = $onAdd;
		foreach ($this->cache[$column][$value] ?? [] as $entity) {
			$onAdd($entity);
		}
	}


	private function indexEntity(CakeEntity $entity, string $column)
	{
		$columnProperty = $this->getColumnProperties()[$column];
		$value = $columnProperty->property->getValue($entity);
		if ($value === null) {
			// nully nejsou v indexu
			return;
		}
		if ( ! isset($this->cache[$column][$value][$entity->getPrimary()])) {
			$this->cache[$column][$value][$entity->getPrimary()] = $entity;
			if ( ! empty($this->onAdd['first'][$column][$value])) {
				Arrays::invoke($this->onAdd['first'][$column][$value], $entity);
				unset($this->onAdd['first'][$column][$value]);
			}
			if ( ! empty($this->onAdd['all'][$column][$value])) {
				Arrays::invoke($this->onAdd['all'][$column][$value], $entity);
			}
		}


	}


	private function createIndex(string $column)
	{
		$this->cache[$column] = [];
	}

	/**
	 * @return ColumnProperty[]
	 */
	private function &getColumnProperties(): array
	{
		return $this->model->getColumnProperties(true);
	}

}