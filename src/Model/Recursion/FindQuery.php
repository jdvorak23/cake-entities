<?php

namespace Cesys\CakeEntities\Model\Recursion;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Cesys\CakeEntities\Model\Entities\EntityHelper;
use Cesys\CakeEntities\Model\Find\Conditions;
use Cesys\CakeEntities\Model\Find\Contains;

/**
 * @internal
 */
class FindQuery extends Query
{
	public Contains $contains;

	/**
	 * @var Contains[]
	 */
	public array $activeContains = [];

	public array $cache = [];

	public array $callbacks = [];

	private bool $isFirstSystem;

	public function __construct(array $fullContains, bool $isFirstSystem)
	{
		$this->contains = Contains::create($fullContains);
		$this->isFirstSystem = $isFirstSystem;
	}

	public function findStart(string $modelClass)
    {
		$this->start($modelClass);
		$this->getFullContains();
    }

    public function findEnd(): bool
    {
		array_pop($this->activeContains);
		return $this->end();
    }

	public function isSystemCall(): bool
	{
		return ! $this->isOriginalCall() || $this->isFirstSystem;
	}


	public function cacheEntity(CakeEntity $entity, bool $doOtherIndexes = true)
	{
		// index na id
		$idColumnProperty = EntityHelper::getColumnProperties(get_class($entity))[$entity::getPrimaryPropertyName()];
		$this->setCache($idColumnProperty->column, $entity);

		if ( ! $doOtherIndexes ) {
			return;
		}

		$columns = $this->getCache();
		// Index na id už máme
		unset($columns[$idColumnProperty->column]);

		foreach (array_keys($columns) as $column) {
			$this->indexEntity($entity, $column);
		}

	}



	public  function getCacheIndex(): int
	{
		return spl_object_id($this->getFullContains());
	}

	public function getCache(?string $column = null): ?array
	{
		$cache = $this->cache[$this->getCurrentModelClass()][$this->getCacheIndex()] ?? null;
		if  ($cache === null) {
			return null;
		}
		if ($column === null) {
			return $cache;
		}
		return $cache[$column] ?? null;
	}

	public function setCache(string $column, $keyValue = null, $value = null): void
	{
		if ($keyValue === null) {
			$this->cache[$this->getCurrentModelClass()][$this->getCacheIndex()][$column] = [];
			return;
		}
		if ($keyValue instanceof CakeEntity) {
			$value = $keyValue;
			$keyValue = $value->getPrimary();
		}
		if ($column === $this->getPrimaryCacheColumn()) {
			$this->cache[$this->getCurrentModelClass()][$this->getCacheIndex()][$column][$keyValue] = $value;
		} elseif ($value === null)  {
			$this->cache[$this->getCurrentModelClass()][$this->getCacheIndex()][$column][$keyValue] = [];
		} else {
			$this->cache[$this->getCurrentModelClass()][$this->getCacheIndex()][$column][$keyValue][$value->getPrimary()] = $value;
		}

	}



	public function addIndex(string $column): bool
	{
		if ($this->getCache($column) === null) {
			$this->setCache($column);
			if ($column !== $this->getPrimaryCacheColumn()) {
				return true;
			}
			if (count($this->cache[$this->getCurrentModelClass()]) > 1) {
				$caches = $this->cache[$this->getCurrentModelClass()];
				array_pop($caches);
				foreach ($caches as $cache) {
					foreach ($cache['id'] ?? [] as $entity) {
						$this->cacheEntity($entity, false);
					}
				}
			}
			return true;
		}

		return false;
	}

	public function indexEntities(string $column) // ne id
	{
		foreach ($this->getCache($this->getPrimaryCacheColumn()) as $entity) {
			if ( ! $entity) {
				continue;
			}
			$this->indexEntity($entity, $column);
		}
	}



	public function getPrimaryCacheColumn()
	{
		return array_key_first($this->getCache());
	}





	private function indexEntity(CakeEntity $entity, string $column)
	{
		$columnProperty = EntityHelper::getColumnPropertiesByColumn(get_class($entity))[$column];
		$value = $columnProperty->property->getValue($entity);
		if ($value === null) {
			// nully nejsou v indexu
			return;
		}

		$this->cache[$entity::getModelClass()][$this->getCacheIndex()][$column][$value][$entity->getPrimary()] = $entity;
	}


	public function getFullContains(array $appendToPath = []): Contains
	{
		$firstCall = count($this->activePath) > count($this->activeContains);
		if ($appendToPath || $firstCall) {
			$contains = $this->contains;
			$path = array_merge($this->activePath, (array_values($appendToPath)));
			array_shift($path);
			foreach ($path as $modelClass) {
				$contains = $contains->contains[$modelClass];
			}
			if ($firstCall) {
				$this->activeContains[$this->getCurrentModelClass()] = $contains;
			}
		} else {
			$contains = $this->activeContains[$this->getCurrentModelClass()];
		}

		return $contains;
	}

/*	public function ()
	{

	}*/

	public function getBackwardContains()
	{
		$interestedModel = $this->getCurrentModelClass();

		$result = [];
		foreach ($this->getFullContains()->contains as $modelClass => $contains) {
			/*if ($modelClass === $interestedModel) {
				continue;
			}*/
			foreach ($contains->contains as $modelContains) {
				if ($modelContains->modelClass === $interestedModel && $this->getCacheIndex() === spl_object_id($modelContains)) {
					$result[] = $modelClass;
				}
			}

		}

		return $result;
	}

}