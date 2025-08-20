<?php

namespace Cesys\CakeEntities\Model\Find;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Cesys\CakeEntities\Model\Entities\ColumnProperty;
use Cesys\CakeEntities\Model\EntityAppModelTrait;
use Cesys\CakeEntities\Model\GetModelTrait;
use Cesys\Utils\Arrays;

class EntityCache
{
	use GetModelTrait;
	public Contains $contains;

	/**
	 * @var EntityAppModelTrait
	 */
	private /*todo*/ $model;
	private string $primaryKey;

	/**
	 * První klíč je název sloupce (~vazebního)
	 * Druhý klíč je hodnota tohoto sloupce
	 * Třetí pole je pole nalezených entit, odpovídající dané hodnotě ~vazebního sloupce, klíč je vždy id entity
	 *
	 * Tedy pokud první klíč bude 'parent_id', druhý klíč bude 1, tak to bude obsahovat všechny entity, které mají `parent_id` = 1
	 * @var array[][][]
	 */
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
	}


	public function add(CakeEntity $entity, array $preparedIndexes = [], ?array $indexOnlyColumns = null)
	{
		if ($indexOnlyColumns === null) {
			$columns = array_keys($this->cache);
		} else {
			foreach ($indexOnlyColumns as $index) {
				// Pokud nebyl vytvořen index, vytvoří se TODO uz jen sameEnts
				$this->addIndex($index);
			}
			$indexOnlyColumns[] = $this->primaryKey; // Vždy
			$columns = array_intersect(array_keys($this->cache), $indexOnlyColumns);
		}

		foreach ($columns as $column) {
			$this->indexEntity($entity, $column, in_array($column, $preparedIndexes, true));
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


	/**
	 * Pokud ještě neexistuje index pro danou hodnotu sloupce, je vytvořen, tj. "pro začátek" je tam 0 prvků a čeká se, co dosype find
	 * @param string $column
	 * @param $value
	 * @return bool Jestli byl vytvořen
	 */
	public function startIndexValue(string $column, $value): bool
	{
		if ( ! isset($this->cache[$column][$value])) {
			$this->cache[$column][$value] = [];
			return true;
		}

		return false;
	}

	/*public function indexEntities(string $column, ?array $ids = null)
	{
		if ($this->primaryKey === $column) {
			return;
		}
		if ($ids === null) {
			foreach ($this->getEntitiesByPrimary() as $entity) {
				$this->indexEntity($entity, $column);
			}
		} else {
			foreach ($ids as $id) {
				if ($entity = $this->getEntity($id)) {
					$this->indexEntity($entity, $column);
				}
			}
		}
	}*/

	/**
	 * @param $value
	 * @param string|null $column
	 * @return CakeEntity|null
	 */
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
		// todo blbe orderasi, vzit z indexu
		return array_intersect_key($this->stash->getCache(), $this->cache[$this->primaryKey]);
	}

	public function &getCache(): ?array
	{
		return $this->cache;
	}

	public function setCache(array &$cache): void
	{
		$this->cache = &$cache;
	}

	public function getCacheByColumn(?string $column = null): ?array
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


	public function indexEntity(CakeEntity $entity, string $column, bool $onlyPrepared = false)
	{
		$columnProperty = $this->getColumnProperties()[$column];
		$value = $columnProperty->property->getValue($entity);
		if ($value === null) {
			// nully nejsou v indexu
			return;
		}
		if ($column !== $this->primaryKey && $onlyPrepared) {
			if ( ! isset($this->cache[$column][$value])) {
				return;
			}
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