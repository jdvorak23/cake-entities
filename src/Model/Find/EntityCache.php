<?php

namespace Cesys\CakeEntities\Model\Find;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Cesys\CakeEntities\Model\Entities\ColumnProperty;
use Cesys\CakeEntities\Model\Entities\EntityHelper;
use Cesys\CakeEntities\Model\EntityAppModelTrait;
use Cesys\CakeEntities\Model\GetModelTrait;
use Cesys\Utils\Arrays;

class EntityCache
{
	use GetModelTrait;
	public FindParams $findParams;

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
	 * Tedy, pokud první klíč bude 'parent_id', druhý klíč bude 1, tak to bude obsahovat všechny entity, které mají `parent_id` = 1
	 * @var CakeEntity[][][]
	 */
	private array $cache = [];

	private array $onAdd = [];

	private array $onAddEntities = [];

	private self $parentEntityCache;

	public function __construct(FindParams $contains)
	{
		$this->findParams = $contains;
		$this->model = $this->getModel($contains->modelClass);
		$this->primaryKey = $this->model->primaryKey;
		$this->cache[$this->primaryKey] = [];
	}


	public static function createFrom(self $fromEntityCache, FindParams $contains): self
	{
		$newCache = new static($contains);
		$newCache->cache = &$fromEntityCache->cache;
		$newCache->onAdd = &$fromEntityCache->onAdd;
		$newCache->parentEntityCache = $fromEntityCache;
		return $newCache;
	}


	public function getParentEntityCache(): ?self
	{
		return $this->parentEntityCache ?? null;
	}


	/**
	 * Vložení entit po volání find
	 * @param CakeEntity[] $entities
	 * @param array $indexOnlyColumns Ve findu nemusely být všechny, které jsou definované, takže vždy jsou zvolené, jinak by se mohl vytvořit neúplný index
	 * @param array $preparedIndexes Do těchto indexů se indexuje hodnota jen v případě, že už má vytvořený klíč (stejný důvod) - tj. v static::startIndexValue()
	 * Jsou vždy vybrány, kromě indexů ve vlastní tabulce s nekonečnými FindParams
	 * @return void
	 */
	public function addAfterFind(array $entities, array $indexOnlyColumns, array $preparedIndexes)
	{
		$indexOnlyColumns[$this->primaryKey] = $this->primaryKey; // Vždy
		foreach ($indexOnlyColumns as $column) {
			$onlyPrepared = in_array($column, $preparedIndexes, true);
			foreach ($entities as $entity) {
				$this->indexEntity($entity, $column, $onlyPrepared);
			}
		}
		Arrays::invoke($this->onAddEntities, $entities);
		$this->onAddEntities = [];
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

		return $this->cache[$column][$value][array_key_first($this->cache[$column][$value])];
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
		return Arrays::flatten($this->cache[$this->primaryKey], true);
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

	public  function setOnNextAddEntities(callable $onNextAddEntities)
	{
		$this->onAddEntities[] = $onNextAddEntities;
	}


	private function indexEntity(CakeEntity $entity, string $column, bool $onlyPrepared = false)
	{
		$columnProperty = EntityHelper::getColumnPropertiesByColumn(get_class($entity))[$column];
		$value = $columnProperty->property->getValue($entity);
		if ($value === null) {
			// nully nejsou v indexu
			return;
		}
		if ($onlyPrepared && $column !== $this->primaryKey) {
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
}