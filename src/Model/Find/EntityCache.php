<?php

namespace Cesys\CakeEntities\Model\Find;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Cesys\CakeEntities\Model\Entities\EntityHelper;
use Cesys\CakeEntities\Model\Entities\RelatedProperty;
use Cesys\CakeEntities\Model\GetModelTrait;
use Cesys\Utils\Arrays;

class EntityCache
{
	/**
	 * Jen kvůli zjištění primaryKey z modelu
	 */
	use GetModelTrait;
	public FindParams $findParams;

	private string $primaryKey;

	private string $entityClass;

	private string $useDbConfig;

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

	/**
	 * Entity, u kterých proběhl nekonečný rekurzivní append do vlastní tabulky
	 * Potřebujeme je znát, protože EntityCache může být sdílená napříč nekonečnými a nikoli nekonečnými contains do vlastní tabulky
	 * Index je spl_object_id, protože potřebujeme jednoznačně identifikovat entitu. Při findu, který je $usecache = false,
	 * se po jeho provedení může sloučit s předchozí cache, a tak dojde k "nahrazení" entit ve sdílené cache,
	 * takže entita, která byla v původní global cache recursivně appendovaná, najednou být po sloučení nemusí
	 * @var CakeEntity[]
	 */
	private array $entitiesRecursiveAppended = [];

	private array $onAddAppendRelatedProperties = [];

	private self $parentEntityCache;

	public function __construct(FindParams $findParams)
	{
		$this->findParams = $findParams;
		$this->primaryKey = $this->getModel($findParams->modelClass)->primaryKey;
		$this->entityClass = $findParams->modelClass::getEntityClass();
		$this->useDbConfig = $findParams->getUseDbConfig();
		$this->cache[$this->primaryKey] = [];
	}



	public static function createFrom(self $fromEntityCache, FindParams $findParams): self
	{
		$newCache = new static($findParams);
		$newCache->cache = &$fromEntityCache->cache;
		$newCache->onAdd = &$fromEntityCache->onAdd;
		$newCache->entitiesRecursiveAppended = &$fromEntityCache->entitiesRecursiveAppended;
		$newCache->parentEntityCache = $fromEntityCache;
		return $newCache;
	}


	public function getUseDbConfig(): string
	{
		return $this->useDbConfig;
	}


	/**
	 * Vložení entit po volání find
	 * @param CakeEntity[] $entities
	 * @param array $indexColumns Ve findu nemusely být všechny, které jsou definované, takže vždy jsou zvolené, jinak by se mohl vytvořit neúplný index
	 * @param array $preparedIndexes Do těchto indexů se indexuje hodnota jen v případě, že už má vytvořený klíč (stejný důvod) - tj. v static::startIndexValue()
	 * Jsou vždy vybrány, kromě indexů ve vlastní tabulce s nekonečnými FindParams
	 * @return void
	 */
	public function addAfterFind(array $entities, array $indexColumns, array $preparedIndexes)
	{
		$indexColumns[$this->primaryKey] = $this->primaryKey; // Vždy
		$preparedIndexes[$this->primaryKey] = $this->primaryKey; // Vždy
		foreach ($indexColumns as $column) {
			// Primární klíč indexujeme vždy
			// Klíč, který není uveden v $preparedIndexes indexujeme taky vždy -> to je v jediném případě, že je find recursive => zde indexy připravit nejdou
			$onlyPrepared = $column !== $this->primaryKey && in_array($column, $preparedIndexes, true);
			$columnProperty = EntityHelper::getColumnPropertiesByColumn($this->entityClass)[$column];
			foreach ($entities as $entity) {
				$value = $columnProperty->property->getValue($entity);
				if ($value === null) {
					// nully nejsou v indexu
					continue;
				}
				if ($onlyPrepared && ! isset($this->cache[$column][$value])) {
					// Kromě primary indexujeme jen připravené hodnoty
					continue;
				}

				$this->indexEntity($entity, $column, $value);
			}
		}

		if ($this->onAddAppendRelatedProperties) {
			$this->appendSameEntities($entities, $this->onAddAppendRelatedProperties);
		}
		$this->onAdd = $this->onAddAppendRelatedProperties = [];
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


	/**
	 * @return CakeEntity[]
	 */
	public function getEntitiesByPrimary(): array
	{
		return Arrays::flatten($this->cache[$this->primaryKey], true);
	}


	public  function setOnAdd(RelatedProperty $relatedProperty, $value, callable $onAdd)
	{
		$column = $relatedProperty->relatedColumnProperty->column;
		if ($relatedProperty->property->getType()->getName() === 'array') {
			if (isset($this->cache[$column][$value])) {
				foreach ($this->cache[$column][$value] as $entity) {
					$onAdd($entity);
				}
			} else {
				$this->onAdd['all'][$column][$value][] = $onAdd;
			}
		} else {
			if (isset($this->cache[$column][$value])) {
				if ($entity = $this->getEntity($value, $column)) {
					$onAdd($entity);
				}
				// Jinak nic, null se přiřazuje předem
			} else {
				$this->onAdd['first'][$column][$value][] = $onAdd;
			}
		}
	}


	/**
	 * Slouží k přiřazení nekonečné rekurze do vlastní tabulky po findu s recursive
	 * @param RelatedProperty $relatedProperty
	 * @return void
	 */
	public function setOnNextAddAppendRelatedProperties(RelatedProperty $relatedProperty)
	{
		$this->onAddAppendRelatedProperties[] = $relatedProperty;
	}


	public function isRecursiveAppended($entity): bool
	{
		return isset($this->entitiesRecursiveAppended[spl_object_id($entity)]);
	}

	public function getParentEntityCache(): ?EntityCache
	{
		return $this->parentEntityCache ?? null;
	}


	public function appendFrom(EntityCache $entityCache)
	{
		foreach($entityCache->getEntitiesByPrimary() as $id => $entity) {
			$this->cache[$this->primaryKey][$id] = [$id => $entity];
			if ($entityCache->isRecursiveAppended($entity)) {
				$this->entitiesRecursiveAppended[spl_object_id($entity)] = $entity;
			}
		}
		foreach ($entityCache->cache as $index => $values) {
			if ($index === $this->primaryKey) {
				continue;
			}
			foreach ($values as $value => $entities) {
				$this->cache[$index][$value] = $entities;
			}
		}
	}


	/**
	 * @param CakeEntity $entity
	 * @param string $column
	 * @param mixed $value nenullová
	 * @return void
	 */
	private function indexEntity(CakeEntity $entity, string $column, $value)
	{
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

	/**
	 * @param array $entities
	 * @param RelatedProperty[] $relatedProperties
	 * @return void
	 */
	private function appendSameEntities(array $entities, array $relatedProperties): void
	{
		foreach ($entities as $entity) {
			$this->entitiesRecursiveAppended[spl_object_id($entity)] = $entity;
			foreach ($relatedProperties as $relatedProperty) {
				$keyPropertyName = $relatedProperty->columnProperty->propertyName;
				$relatedColumn = $relatedProperty->relatedColumnProperty->column;
				if ($relatedProperty->property->isInitialized($entity)) {
					continue;
				}
				if ( ! isset($entity->{$keyPropertyName})) {
					if ($relatedProperty->property->getType()->getName() === 'array') {
						// 1:M
						$relatedProperty->property->setValue($entity, []);
					} elseif ($relatedProperty->property->getType()->allowsNull()) {
						// 1:1, nullable
						$relatedProperty->property->setValue($entity, null);
					}
					continue;
				}

				$value = EntityHelper::getPropertyDbValue($entity, $relatedProperty->columnProperty);

				if ($relatedProperty->property->getType()->getName() === 'array') {
					// 1:M
					$relatedEntities = $this->getEntities($value, $relatedColumn);
					if ( ! $relatedEntities) {
						// Pokud nejsou žádné 1:M entity, nebyl vytvořen na tuto hodnotu index,
						// protože pro recursive si hodnoty nepřipravujeme (nejde to)
						// Pokud nějaké vazební entity existují, byl index hodnoty vytvořen v static::addAfterFind
						// Takže doplníme []
						$this->cache[$relatedColumn][$value] = [];
					}
					$relatedProperty->property->setValue($entity, $relatedEntities);
				} else {
					// 1:1
					if ($otherEntity = $this->getEntity($value, $relatedColumn)) {
						$relatedProperty->property->setValue($entity, $otherEntity);
					} else {
						if ($relatedProperty->property->getType()->allowsNull()) {
							$relatedProperty->property->setValue($entity, null);
						}
						// Zde to samé, jako výše => index na prázdnou hodnotu nebyl vytvořen, vytvoříme
						$this->cache[$relatedColumn][$value] = [];
					}
				}
			}
		}
	}
}