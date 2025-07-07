<?php

namespace Cesys\CakeEntities\Model;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Cesys\CakeEntities\Model\Entities\ColumnProperty;
use Cesys\CakeEntities\Model\Entities\EntityHelper;
use Cesys\CakeEntities\Model\Entities\RelatedProperty;
use Cesys\CakeEntities\Model\Find\Conditions;
use Cesys\CakeEntities\Model\Find\Params;
use Cesys\CakeEntities\Model\LazyModel\ModelLazyModelTrait;
use Cesys\CakeEntities\Model\Recursion\FindQuery;
use Cesys\CakeEntities\Model\Recursion\Query;
use Cesys\Utils\Arrays;
use Cesys\Utils\Reflection;

/**
 * Pokud v use tříde je definován konstruktor, je v něm potřeba volat $this->lazyModelTraitConstructor();
 * @template E of CakeEntity
 */
trait EntityAppModelTrait
{
    /**
     * Potřebujeme, aby save probíhal řádně
     * + pro přepisování saveEntity se nám hodí implementace lastSaveResult
     */
    use SaveRepairTrait;

    /**
     * Měl by být standard
     */
    use ModelLazyModelTrait;

	static private array $SERVER_DEFAULT_SUB_NAMESPACE = [
		's_server2' => 'server',
		't_server2' => 'server',
		'u_server2' => 'server',
	];

    protected array $contains = [];

	protected bool $isStatic = false;

	/**
	 * @var array|false
	 */
	protected $fetchedAll = false;

    /**
     * Cache fetchnutých / uložených entit
     * @var array
     */
    protected array $entities = [];


    /**
     * @var array|null
     */
    private ?array $temporaryContains = null;

    /**
     * Uložené default hodnoty všech sloupců tabulky, které mají default
     * @var array
     */
    private array $defaults;

	private array $columnProperties;

	private array $fields;




    /**
	 * Získání třídy Entity, pokud není vše ve standardním namespace, je nutno v modelu přetížit
	 * @return class-string<E>
	 */
    abstract public static function getEntityClass(): string;


    public function getFullContains(?array $contains = null, bool $resetTemporaryContains = false): ?array
    {
		static $query;
		if ( ! isset($query)) {
			$query = new Query();
		}
		$query->start(static::class);

        if ( ! $query->isFirstModelInActivePath() && $contains === null) {
            // Jsme v rekurzi + defaultní $contains, tj. to už se vrátilo
			$query->end();
            return null;
        }

		$contains = $this->getNormalizedContains($contains, $this->temporaryContains);

		//bdump($contains);
        if ( ! $contains) {
            // Tj. nic
			if ($query->end()) {
				// Toto pokud ten první model má []
				if ($resetTemporaryContains) {
					foreach (array_unique($query->path) as $modelClass) {
						/** @var static $Model */
						$Model = $this->getModel($modelClass);
						$Model->setTemporaryContains();
					}
				}

				$query = null;
				$contains = [static::class => ['contains' => $contains]];
			}
            return $contains;
        }

        $containedModels = array_keys($contains);

		// todo musí se dělat se zkontrolovaným schéma jeste pres model, pze pak pokud neni ve schema jsou errory, pze by se muselo "odebrat"
		$relatedProperties = array_merge(
			EntityHelper::getPropertiesOfReferencedEntities(static::getEntityClass()),
			EntityHelper::getPropertiesOfRelatedEntities(static::getEntityClass())
		);

		// Odebereme z $contains všechny klíče (= modely), co nejsou v $relatedProperties
		$modelsInRelatedProperties = array_map(fn(RelatedProperty $relatedProperty) => $relatedProperty->relatedColumnProperty->entityClass::getModelClass(), $relatedProperties);
		$contains = array_intersect_key($contains, array_flip($modelsInRelatedProperties));

        foreach ($relatedProperties as $relatedProperty) {
            $modelClass = $relatedProperty->relatedColumnProperty->entityClass::getModelClass();
            $index = array_search($modelClass, $containedModels, true);
            if ($index === false) {
                continue;
            }
            unset($containedModels[$index]);

            /** @var static $Model */
            $Model = $this->getModel($modelClass);
            if ( ! in_array(EntityAppModelTrait::class, Reflection::getUsedTraits(get_class($Model)))) {
				// todo interface
                throw new \InvalidArgumentException("Model '$modelClass' included in \$contains parameter is not instance of EntityAppModel.");
            }
			if ( ! $this->schema($relatedProperty->columnProperty->column)) {
				// Musí být sloupec ve schema
				continue;
			}
			if ( ! $Model->schema($relatedProperty->relatedColumnProperty->column)) {
				// Musí být sloupec ve schema
				continue;
			}


			if (isset($contains[$modelClass]) && ! array_key_exists('contains', $contains[$modelClass])) {
				// Pole existuje, ale nemá prvek 'contains' => contains je defaultně []
				$contains[$modelClass]['contains'] = [];
			}

			$contains[$modelClass]['contains'] = $Model->getFullContains($contains[$modelClass]['contains'] ?? null);
        }



		if ($query->end()) {
			//bdump($query->path);
			if ($resetTemporaryContains) {
				foreach (array_unique($query->path) as $modelClass) {
					/** @var static $Model */
					$Model = $this->getModel($modelClass);
					$Model->setTemporaryContains();
				}
			}

			$query = null;
			$contains = [static::class => ['contains' => $contains]];
		}

        return $contains;
    }


    /**
     * @param array $contains
     * @return array původní $contains
     */
    public function setContains(array $contains): array
    {
        $originalContains = $this->contains;
        $this->contains = $contains;
        return $originalContains;
    }


    /**
     * Platí jen pro první volání findEntities, včetně volání na připojení entit
     * @param ?array $contains null => reset (když je null, nic to nedělá)
     * @return void
     */
    public function setTemporaryContains(?array $contains = null): void
    {
        $this->temporaryContains = $contains;
    }


	public function clearCache(): void
	{
		$this->entities = [];
	}


    /**
     * @param array $params
     * @param array|null $contains
     * @param bool $useCache
     * @return ?E
     */
    public function findEntity(array $params = [], ?array $contains = null, bool $useCache = false)
    {
        $params['limit'] = 1;
        $entities = $this->findEntities($params, $contains, $useCache);
        return $entities ? current($entities) : null;
    }


    /**
     * @param $id
     * @param array|null $contains
     * @param bool $useCache
     * @return ?E
     */
    public function getEntity($id, ?array $contains = null, bool $useCache = true)
    {
        $entities = $this->getEntities([$id], $contains, $useCache);
        return $entities ? current($entities) : null;
    }

    /**
     * @var FindQuery[]
     */
    private static array $findQueries = [];

    private function getFindQuery(): ?FindQuery
    {
        $debugBacktrace = debug_backtrace(!DEBUG_BACKTRACE_PROVIDE_OBJECT|DEBUG_BACKTRACE_IGNORE_ARGS,3);
		//bdump($debugBacktrace[2]['function']);
        if (
            in_array($debugBacktrace[1]['function'], ['addOtherEntities', 'addSameEntities', 'addSameEntitiesRecursive','buildSqlSubquery', 'appendSameEntities'])
            || in_array($debugBacktrace[2]['function'], ['addOtherEntities', 'addSameEntities', 'addSameEntitiesRecursive', 'appendSameEntities'])
			|| ($debugBacktrace[1]['function'] === $debugBacktrace[2]['function'] && $debugBacktrace[1]['function'] === 'findEntities')
        ) {
			return self::$findQueries[array_key_last(self::$findQueries)];
        }

		return null;
    }

	private function createFindQuery(array $fullContains, bool $isSystem): FindQuery
	{
		$findQuery = new FindQuery($fullContains, $isSystem);
		self::$findQueries[] = $findQuery;
		return $findQuery;
	}

	private function buildSqlSubquery(): string
	{
		$params = $this->getFindQuery()->getFullContains()->params->toArray();
		$params['table'] = $this->getDataSource()->fullTableName($this);
		$params['alias'] = 'inner_' . $this->getDataSource()->fullTableName($this, false);
		$params['fields'] = [$this->primaryKey];
		return trim($this->getDataSource()->buildStatement($params, $this));
	}

    /**
     * @param array $params
     * @param array|null $contains
     * @param bool $useCache
     * @return E[]
     */
    public function findEntities(array $params = [], ?array $contains = null, bool $useCache = false): array
    {

        if ( ! $findQuery = $this->getFindQuery()) {
			// Nové volání, inicializace FindQuery
			$fullContains = $this->getFullContains($contains, true);
			$isSystem = debug_backtrace(! DEBUG_BACKTRACE_PROVIDE_OBJECT|DEBUG_BACKTRACE_IGNORE_ARGS,2)[1]['function'] === 'getEntities';
			$findQuery = $this->createFindQuery($fullContains, $isSystem);
			//bdump($this->schema());
		}


		$findQuery->findStart(static::class);

		$fullContains = $findQuery->getFullContains();

		if (count($findQuery->activePath) > 50) { // TODO
			bdump("DOPICICI");
			$findQuery->findEnd();
			return [];
		}

		if ($findQuery->isOriginalCall()) {
			// Uživatelská params, vytvoříme nové
			$fullContains->params = Params::create($params);
			// I kdyby uživatel nastavil něco jiného, je to -1
			$fullContains->params->setRecursive(-1);

		}

		// Params jsou vždy všechny co jsou na entitě, jinak psycho
		$fullContains->params->setFields($this->getFields());

		if ($findQuery->isSystemCall()) {
			// todo system
			// K prvnímu volání nemůžeme přiřazovat entity, params můžou být různorodé, nevíme potom na konci jaké entity vrátit a order
			if ($fullContains->contains) {
				if ($backwardContains = $findQuery->getBackwardContains()) {
					if ( in_array( static::class, $backwardContains)) {
						//bdump($backwardContains);
					}
				}
			}
		}

		//bdump($this->buildSqlSubquery());
		//bdump($fullContains->params->getConditions());
		if ($findQuery->isSystemCall() && ! $fullContains->params->getConditions()->getOr()->isEmpty()) {
			// Uživatelovo volání neupravujeme, projdeme OR, pokud jsou
			// Projdeme všechny OR conditions sloupce
		//	$columnsToReindex = [];
			foreach ($fullContains->params->getConditions()->getOr()->keyConditions as $column => $values) {
				$entityCache = $findQuery->getEntityCache();
				bdump($entityCache);
				$entityCache->addIndex($column);
				foreach ($values as $key => $id) {
					if ( ! $entityCache->startIndexValue($column, $id)) {
						unset($fullContains->params->getConditions()->getOr()->keyConditions[$column][$key]);
					}
				}
				if ( ! $fullContains->params->getConditions()->getOr()->keyConditions[$column]) {
					unset($fullContains->params->getConditions()->getOr()->keyConditions[$column]);
				}
			}
			//bdump($fullContains->params->getConditions());

			/*foreach ($columnsToReindex as $column) {
				// todo neni treba
				$findQuery->indexEntities($column);
			}*/
		}



		if (
			isset($fullContains->contains[static::class])
			&& ! $fullContains->params->getConditions()->stringConditions
			&& ! $fullContains->params->getConditions()->isEmpty()
			&& $findQuery->isSystemCall()
			&& ! $this->isStatic
		) {
			// Řešíme rekurzi do vlastní tabulky, ale ne tu, kde už je to v conditions
			$relatedContains = $fullContains->contains[static::class];
			if ($fullContains === $relatedContains) {
				// Stejné contains a systémové -> přidáme sql na rekurzi do vl. tabulky
				$this->addSameEntitiesRecursive([], $useCache, true);
				/*$this->addSameEntities($entities, $useCache);
				if ( ! $relatedContains->params->getConditions()->isEmpty()) {
					$this->findEntities([], [], $useCache);
				}*/
			}

		}

		$entities = [];

		if ($this->isStatic) {
			if ($this->fetchedAll !== false) {
				foreach ($this->fetchedAll as $entity) {
					$findQuery->getEntityCache()->add($entity);
				}
			} else {
				$this->fetchedAll = [];
				$params = $fullContains->params->toArray();
				$params['conditions'] = [];
				$entitiesData = $this->find('all', $params);
				foreach ($entitiesData as $entityData) {
					$entity = $this->createEntity($entityData);
					$this->entities[$entity->getPrimary()] = $entity;
					$this->fetchedAll[$entity->getPrimary()] = $entity;
					$findQuery->getEntityCache()->add($entity);
				}
				$this->appendSameEntities();
			}
			if ($findQuery->isOriginalCall()) {
				//system.
				if ($fullContains->params->getConditions()->isEmpty()) {
					$entities = $this->fetchedAll;
				} else {
					$params = $fullContains->params->toArray();
					$params['fields'] = [$this->primaryKey, $this->primaryKey];
					$idsInOrder = $this->find('list', $params);
					foreach ($idsInOrder as $id) {
						$entities[$id] = $this->fetchedAll[$id];
					}
				}

			}

			$fullContains->params->clear();
		}

		if ( ! $this->isStatic
			&& ( ! $findQuery->isSystemCall() || ! $fullContains->params->getConditions()->isEmpty())) {
			// Uživatelovo volání, nebo když je neco v conditions
			$entitiesData = $this->find('all', $fullContains->params->toArray());
			$allPrim = [];
			foreach ($entitiesData as $entityData) {
				$primary = $entityData[$this->alias][$this->primaryKey];
				$allPrim[] = $primary;
				if (isset($findQuery->getStash()->getCache()[$primary])) {
					// Pokud je fetchnuta znova entita, která už je v cache, nepřepisuje se!
					$entity = $findQuery->getStash()->getCache()[$primary];
				} else {
					$entity = $this->createEntity($entityData);
					$this->entities[$entity->getPrimary()] = $entity;
				}

				$entities[$entity->getPrimary()] = $entity;
				// Takto se ujistíme, že i když entita tam už je, že bude na všech indexech
				$findQuery->getEntityCache()->add($entity);
			}
			//bdump($allPrim);
		}

		if ($findQuery->isSystemCall() && $fullContains->params->getConditions()->stringConditions) {
			// Bylo volání WITH RECURSIVE
			// Přiřazení všeho
			$this->appendSameEntities();
		}
		$fullContains->params->conditions = null;
		if ($findQuery->isOriginalCall()) {
			// nonSystem
			$fullContains->params->clear();
		}

bdump($findQuery->isRecursiveToSelf());
		if ($findQuery->isRecursiveToSelf() && ! $this->isStatic) {
			// Řešíme rekurzi do vlastní tabulky
			$relatedContains = $fullContains->contains[static::class];

			if ($findQuery->isRecursionToSelfEndless()) {
				if ( ! $findQuery->isSystemCall()) {
					// První volání, kde se před find nedoplňují RECURSIVE && nekonečné contains => chceme RECURSIVE
					$this->addSameEntitiesRecursive($entities, $useCache);
					if ( ! $relatedContains->params->getConditions()->isEmpty()) {
						$this->findEntities([], [], $useCache);
					}
				}
			} else {
				// Jsou konečné contains, pouze flat
				$this->addSameEntities($entities);
				if ( ! $relatedContains->params->getConditions()->isEmpty()) {
					$this->findEntities([], [], $useCache);
				}
			}
			// todo první volání a stejné contains -> připojit
		}



        // Nalezení entit ve vlastní tabulce (přes nějaké parent_id)
        //$allEntities = $this->addSameEntities($entities, $useCache);
        // Připojení entit z ostatních tabulek
        $this->addOtherEntities($entities, $useCache);
		bdump($fullContains->contains);
		foreach ($fullContains->contains as $modelClass => $modelContains) {
			if ($modelContains->params->getConditions()->isEmpty()) {
				continue;
			}
			/** @var static $Model */
			$Model = $this->getModel($modelClass);
			$Model->findEntities([], [], $useCache);
		}


        if ($findQuery->findEnd()) {
            bdump($findQuery, static::class);
			array_pop(self::$findQueries);
            return $entities;
        }

        return [];
    }


    /**
     * @param array $ids
     * @param array|null $contains
     * @param bool $useCache
     * @return E[]
     */
    public function getEntities(array $ids, ?array $contains = null, bool $useCache = true): array
    {
        $ids = $this->filterIds($ids);
        if ( ! $ids) {
            return [];
        }

        $this->findEntities([
            'conditions' => [
                'OR' => [
                    $this->primaryKey => $ids
                ]
            ]
        ], $contains, $useCache);

        $result = [];
        foreach ($ids as $id) {
            if (isset($this->entities[$id])) {
                $result[$id] = $this->entities[$id];
            }
        }

        return $result;
    }


    /**
     * @param ?array $data
     * @return E
     */
    public function createEntity(?array $data = null)
    {
        if ($data === null) {
            $data = $this->getDefaults();
        } else {
            $data = $data[$this->alias] ?? $data;
        }
        /** @var class-string<E> $entityClass */
        $entityClass = static::getEntityClass();
        return $entityClass::createFromDbArray($data);
    }


    /**
     * @param E $entity
     * @param array $appendData
     * @param bool $validate
     * @param array $fieldList
     * @return bool
     */
    public function saveEntity(CakeEntity $entity, bool $validate = true, array $fieldList = [], array $appendData = []): bool
    {
        // todo overeni class
        $data = $entity->toDbArray();
        if ($entity->getPrimary() === null) {
            $this->id = false; // todo create?
        }
        $result = $this->save([$this->alias => array_merge($data, $appendData)], $validate, $fieldList);
        
        if ($result) {
            // Pokud nebylo id, přiřadí se
            if ($entity->getPrimary() === null) {
                $entity->setPrimary($this->id);
            }

            // Todo mozna priradit i zbytek co se vratilo, ale zatím není jistý, pokud chceme defaulty, meli bychom entitu spravne vytvaret
            if (isset($result[$this->alias]['created']) && ($columnProperty = $this->getColumnProperties()['created'] ?? null)) {
				EntityHelper::appendFromDbValue($entity, $columnProperty, $result[$this->alias]['created']);
            }
            if (isset($result[$this->alias]['modified'])  && ($columnProperty = $this->getColumnProperties()['modified'] ?? null)) {
				EntityHelper::appendFromDbValue($entity, $columnProperty, $result[$this->alias]['modified']);
            }

            $this->entities[$entity->getPrimary()] = $entity;
        }

        return (bool) $result;
    }


    /**
     * @param array $paginateParams
     * @param array $conditions
     * @return array $paginate
     */
    public function paginateIds(array $paginateParams, array $conditions): array
    {
        $paginateParams['conditions'] = $conditions; // todo?
        $paginateParams['fields'] = ["$this->alias.$this->primaryKey"];

        if (isset($paginateParams['contains'])) {
            $joins = $this->getJoins($paginateParams['contains'], $this);
        } else {
            return $paginateParams;
        }
        unset($paginateParams['contains']);

        $models = array_keys($joins);
        $joins = array_intersect_key($joins, array_flip($this->findModelsInConditions($models, $conditions)));
        $finalJoins = [];
        foreach ($joins as $join) {
            $finalJoins = array_merge($join, $finalJoins);
        }
        $paginateParams['joins'] = array_values($finalJoins);
        $paginateParams['group'] = ["$this->alias.$this->primaryKey"];

        return $paginateParams;
    }


    /**
     * Profiltruje pole aby v něm byly jen int a string max 1x
     * @param array $ids
     * @return string[]|int[]
     */
    protected function filterIds(array $ids): array
    {
        return array_values(array_unique(array_filter($ids, fn($value) => is_int($value) || is_string($value))));
    }


    public function getDefaults(): array
    {
        if (isset($this->defaults)) {
            return $this->defaults;
        }
        $this->defaults = [];
        foreach ($this->schema() as $column => $schema) {
            if ($schema['default'] === null && ! $schema['null']) {
                continue;
            }
			// Tyto můžeme mít v entitách ne-nullable, ale v defaults jsou vetsinou s null, takže natvrdo ošetřeno
            if (in_array($column, ['created', 'modified', 'created_by', 'modified_by'], true)) {
                continue;
            }
            $this->defaults[$column] = $schema['default'];
        }

        return $this->defaults;
    }


	/**
	 * @return ColumnProperty[]
	 */
	public function &getColumnProperties(bool $byColumn = false): array
	{
		$index = $byColumn ? 'column' : 'property';
		if (isset($this->columnProperties)) {
			return $this->columnProperties[$index];
		}

		$this->columnProperties = [
			'column' => [],
			'property' => [],
		];
		foreach(EntityHelper::getColumnProperties(static::getEntityClass()) as $columnProperty) {
			if ($columnSchema = $this->schema($columnProperty->column)) {
				if ($columnSchema['type'] === 'date') {
					$columnProperty->isDateOnly = true;
				}
				$this->columnProperties['column'][$columnProperty->column] = $columnProperty;
				$this->columnProperties['property'][$columnProperty->propertyName] = $columnProperty;
			}
		}

		return $this->columnProperties[$index];
	}


	public function getFields(): array
	{
		// Třída entity modelu se může změnit todo ne
		$entityClass = static::getEntityClass();
		if (isset($this->fields[$entityClass])) {
			return $this->fields[$entityClass];
		}

		$this->fields[$entityClass] = [];
		foreach ($this->getColumnProperties() as $columnProperty) {
			$this->fields[$entityClass][] = $columnProperty->column;
		}

		return $this->fields[$entityClass];
	}


    private function getNormalizedContains(?array $contains = null, ?array $temporaryContains = null): array
    {
        $normalizedContains = [];
        $defaultContains = $temporaryContains ?? $this->contains;
        foreach ($contains ?? $defaultContains as $key => $value) {
            if (is_array($value) || $value === null) {
                $normalizedContains[$key] = $value;
            } elseif (is_string($value)) {
                $normalizedContains[$value] = null;
            }
        }

        return $normalizedContains;
    }


	private function addSameEntities(array $entities): void
	{
		$findQuery = $this->getFindQuery();
		if ( ! $entities) {
			return;
		}

		if ( ! array_key_exists(static::class, $findQuery->getFullContains()->contains)) {
			return;
		}

		// Najdeme modelovo contains
		$modelContains = $findQuery->getFullContains([static::class]);
		/*bdump($findQuery->getFullContains()->params->isEqualTo($modelContains->params), "PIIIDID");
		bdump($findQuery->getFullContains()->params);
		bdump($modelContains->params);*/
		if ($relatedModelContains = $modelContains->contains[static::class] ?? null) {

		}
		foreach (EntityHelper::getPropertiesOfSelfEntities(static::getEntityClass()) as $relatedProperty) {
			$keyPropertyName = $relatedProperty->columnProperty->propertyName;
			// Vazební sloupec
			$relatedColumn = $relatedProperty->relatedColumnProperty->column;
			if ($relatedProperty->property->getType()->getName() === 'array') {
				foreach ($entities as $entity) {
					if ($relatedProperty->property->isInitialized($entity)) {
						continue;
					}
					$relatedProperty->property->setValue($entity, []);
					if (isset($entity->{$keyPropertyName})) {
						// Spojovací klíč má hodnotu
						$value = $entity->{$keyPropertyName};
						// Přidáme do conditions
						$modelContains->params->getConditions()->addOrCondition($relatedColumn, $value);
						$findQuery->getEntityCache($modelContains)->setOnAdd($relatedColumn, $value, false, function (CakeEntity $otherEntity) use ($relatedProperty, $entity) {
							$entity->{$relatedProperty->property->getName()}[$otherEntity->getPrimary()] = $otherEntity;
						});
					}
				}
			} else {
				foreach ($entities as $entity) {
					if ($relatedProperty->property->isInitialized($entity)) {
						continue;
					}
					if ($relatedProperty->property->getType()->allowsNull()) {
						$relatedProperty->property->setValue($entity, null);
					}
					if (isset($entity->{$keyPropertyName})) {
						// Spojovací klíč má hodnotu // todo teoreticky datum, takze spis hodnotu sloupce nejak
						$value = $entity->{$keyPropertyName};
						// Přidáme do conditions
						$modelContains->params->getConditions()->addOrCondition($relatedColumn, $value);

						$findQuery->getEntityCache($modelContains)->setOnAdd($relatedColumn, $value, true, function (CakeEntity $otherEntity) use ($relatedProperty, $entity) {
							$relatedProperty->property->setValue($entity, $otherEntity);
							// Zůstane unset, pokud není nullable a append je null. Může se pak asi točit - performance?
						});


						if ( ! $relatedModelContains = $modelContains->contains[static::class] ?? null) {
							continue;
						}
						if ( ! $relatedModelContains) {
							continue;
						}

						bdump("coj");
						bdump($value, $relatedColumn);
						bdump($modelContains->contains);

						foreach (EntityHelper::getPropertiesOfSelfEntities(static::getEntityClass()) as $relatedEntityRelatedProperty) {
							if ($relatedEntityRelatedProperty->columnProperty->column === $relatedColumn) {
								bdump($relatedEntityRelatedProperty, "ANO");
								$relatedModelContains = $modelContains->contains[static::class];
								$modelContains->params->getConditions()->addOrCondition($relatedEntityRelatedProperty->relatedColumnProperty->column, $value);
							} else {
								bdump("NE");
							}
						}

					}
				}
			}
		}
	}


    /**
     * @param E[] $entities
     * @param bool $useCache
     * @return void
     */
    private function addSameEntitiesRecursive(array $entities, bool $useCache, bool $fromSubQuery = false): void
    {
		$findQuery = $this->getFindQuery();
		if ($fromSubQuery) {
			$entities = [];
			if ($findQuery->getFullContains()->params->getConditions()->isEmpty()) {
				// subquery nemůže být bez conditions
				return;
			}
		} elseif ( ! $entities) {
            return;
        }


		if ( ! array_key_exists(static::class, $findQuery->getFullContains()->contains)) {
			return;
		}

        $selects = [
            'ascendants' => [],
            'descendants' => [],
        ];
        $idsToFetch = [];
        foreach (EntityHelper::getPropertiesOfSelfEntities(static::getEntityClass()) as $relatedProperty) {
            if( ! $this->schema($relatedProperty->relatedColumnProperty->column)) {
                // Musí být sloupec ve schema todo mozna vsude obdobne
                continue;
            }
			$keyPropertyName = $relatedProperty->columnProperty->propertyName;
            foreach ($entities as $entity) {
                if ($relatedProperty->property->isInitialized($entity)) {
                    continue;
                }
				if ( ! isset($entity->{$keyPropertyName})) {
					continue;
				}
                $idsToFetch[] = $entity->getPrimary();
            }
			if ($relatedProperty->property->getType()->getName() === 'array') {
				$selects['descendants'][] = [$relatedProperty->relatedColumnProperty->column, $relatedProperty->columnProperty->column];
			} else {
				$selects['ascendants'][] = [$relatedProperty->columnProperty->column, $relatedProperty->relatedColumnProperty->column];
			}
        }

		if ( ! $fromSubQuery) {
			$idsToFetch = $this->filterIds($idsToFetch);
			if ( ! $idsToFetch) {
				return;
			}
		}

		$ascendantsBindingColumns = array_unique(Arrays::flatten($selects['ascendants']));
		$descendantsBindingColumns = array_unique(Arrays::flatten($selects['descendants']));
		$ascendantsTBindingColumns = array_map(fn($column) => "t.$column", $ascendantsBindingColumns);
		$descendantsTBindingColumns = array_map(fn($column) => "t.$column", $descendantsBindingColumns);
		$ascendantsBindingColumns = implode(', ', $ascendantsBindingColumns);
		$descendantsBindingColumns = implode(', ', $descendantsBindingColumns);
		$ascendantsTBindingColumns = implode(', ', $ascendantsTBindingColumns);
		$descendantsTBindingColumns = implode(', ', $descendantsTBindingColumns);
		$ascendantsOns = [];
		$descendantsOns = [];
		foreach ($selects['ascendants'] as [$column, $bindingColumn]) {
			$ascendantsOns[] = "t.$bindingColumn = ascendants.$column";
		}
		foreach ($selects['descendants'] as [$bindingColumn, $column]) {
			$descendantsOns[] = "t.$bindingColumn = descendants.$column";
		}

		$sql = 'WITH RECURSIVE';
		$sqlEnd = '';
		$subquery = null;
		if ($fromSubQuery) {
			$conditions = $findQuery->getFullContains()->params->getConditions();
			if (count($conditions->getOr()->keyConditions) === 1 && isset($conditions->getOr()->keyConditions[$this->primaryKey])) {
				$idsToFetch = $conditions->getOr()->keyConditions[$this->primaryKey];
			} else {
				$subquery = $this->buildSqlSubquery();
				$sql .= "
				base_id AS (
					$subquery
				),";
			}
		}

		$values = implode(', ', $idsToFetch);
		$where = $subquery ? "$this->primaryKey IN (SELECT $this->primaryKey FROM base_id)" : "$this->primaryKey IN ($values)";
		if ($ascendantsOns) {
			$ascendantsOnsText = implode(' OR ', $ascendantsOns);
			$sql .= "
				ascendants AS (
					SELECT $ascendantsBindingColumns
					FROM $this->useTable
					WHERE $where
					UNION ALL
					SELECT $ascendantsTBindingColumns
					FROM $this->useTable t
					INNER JOIN ascendants ON $ascendantsOnsText
				)";
			$sqlEnd .= "
				SELECT DISTINCT id
				FROM ascendants
				";
		}
		if ($descendantsOns) {
			if ($ascendantsOns) {
				$sql .= ',';
			}
			$descendantsOnsText = implode(' OR ', $descendantsOns);
			$sql .= "
				descendants AS (
					SELECT $descendantsBindingColumns
					FROM $this->useTable
					WHERE $where
					UNION ALL
					SELECT $descendantsTBindingColumns
					FROM books t
					INNER JOIN descendants ON $descendantsOnsText
				)";
			if ($ascendantsOns) {
				$sqlEnd .= 'UNION';
			}
			$sqlEnd .= "
				SELECT DISTINCT id
				FROM descendants
			";
		}
		$sql .= $sqlEnd;
		if ($fromSubQuery) {
			$findQuery->getFullContains()->params->conditions = null;
			// Tyto už máme
			if ($not = $findQuery->getStash()->getCache()) {
				$findQuery->getFullContains()->params->getConditions()->not = Conditions::create([$this->primaryKey => array_keys($not)]);
			}
			$findQuery->getFullContains()->params->getConditions()->stringConditions[] = "$this->primaryKey IN ($sql)";
			return;
		}
		// Tyto už máme
		if ($not = $findQuery->getStash()->getCache()) {
			$findQuery->getFullContains([static::class])->params->getConditions()->not = Conditions::create([$this->primaryKey => array_keys($not)]);
		}
		$findQuery->getFullContains([static::class])->params->getConditions()->stringConditions[] = "$this->primaryKey IN ($sql)";
    }

	private function appendSameEntities()
	{
		$findQuery = $this->getFindQuery();
		$entityCache = $findQuery->getEntityCache();
		if ( ! $entities = $entityCache->getEntitiesByPrimary()) {
			return;
		}
		bdump($entities);
		bdump($findQuery);
		exit;

		foreach (EntityHelper::getPropertiesOfSelfEntities(static::getEntityClass()) as $relatedProperty) {
			$keyPropertyName = $relatedProperty->columnProperty->propertyName;
			$relatedColumn = $relatedProperty->relatedColumnProperty->column;
			if ($entityCache->addIndex($relatedColumn)) {
				// Musíme naindexovat, pokud spojujeme jinam nez na id
				$entityCache->indexEntities($relatedColumn);
			}

			foreach ($entities as $entity) {
				if ($relatedProperty->property->isInitialized($entity)) {
					continue;
				}

				if ( ! isset($entity->{$keyPropertyName})) {
					if ($relatedProperty->property->getType()->getName()  === 'array') {
						$relatedProperty->property->setValue($entity, []);
					} elseif ($relatedProperty->property->getType()->allowsNull()) {
						$relatedProperty->property->setValue($entity, null);
					}
					continue;
				}

				$value = EntityHelper::getPropertyDbValue($entity, $relatedProperty->columnProperty);

				if ($relatedProperty->property->getType()->getName() === 'array') {
					$relatedProperty->property->setValue($entity, $entityCache->getEntities($value, $relatedColumn));
				} else {
					if ($otherEntity = $entityCache->getEntity($value, $relatedColumn)) {
						$relatedProperty->property->setValue($entity, $otherEntity);
						continue;
					}

					if ($relatedProperty->property->getType()->allowsNull()) {
						$relatedProperty->property->setValue($entity, null);
					}
				}
			}
		}

	}

    /**
     * @param E[] $entities
     * @param ?array $contains
     * @param bool $useCache
     */
    private function addOtherEntities(array $entities, bool $useCache): void
    {
        if ( ! $entities) {
            return;
        }

        $findQuery = $this->getFindQuery();

		$fullContains = $findQuery->getFullContains();
		/*bdump($fullContains, static::class);
		bdump($findQuery);*/

        if ( ! $fullContains->contains) {
            return;
        }
		bdump($findQuery, static::class);
		bdump($entities);
		bdump($fullContains);

        $containedModels = array_keys($fullContains->contains);
		bdump(EntityHelper::getPropertiesOfOtherEntities(static::getEntityClass(), $containedModels) );
		exit;
		foreach (EntityHelper::getPropertiesOfOtherEntities(static::getEntityClass(), $containedModels) as $relatedProperty) {
			$modelClass = $relatedProperty->relatedColumnProperty->entityClass::getModelClass();
			/** @var static $Model */
			$Model = $this->getModel($modelClass);
			if ( ! in_array(EntityAppModelTrait::class, Reflection::getUsedTraits(get_class($Model)))) {
				throw new \InvalidArgumentException("Model '$modelClass' included in \$contains parameter is not instance of EntityAppModel.");
			}
			// todo wtf
			if ( ! $Model->schema($relatedProperty->relatedColumnProperty->column)) {
				// Musí být sloupec ve schema
				continue;
			}

			$keyPropertyName = $relatedProperty->columnProperty->propertyName;
			// Vazební sloupec v cizí tabulce
			$relatedColumn = $relatedProperty->relatedColumnProperty->column;
			// Najdeme modelovo contains
			$modelContains = $findQuery->getFullContains([$modelClass]);

			if ($relatedProperty->property->getType()->getName() === 'array') {
				// Relace []
				foreach ($entities as $entity) {
					if ($relatedProperty->property->isInitialized($entity)) {
						continue;
					}
					if (isset($entity->{$keyPropertyName})) {
						// Spojovací klíč má hodnotu
						$value = $entity->{$keyPropertyName};
						// Přidáme do conditions
						$modelContains->params->getConditions()->addOrCondition($relatedColumn, $value);

						$findQuery->getEntityCache($modelContains)->setOnAdd($relatedColumn, $value, false, function (CakeEntity $otherEntity) use ($relatedProperty, $entity) {
							$entity->{$relatedProperty->property->getName()}[$otherEntity->getPrimary()] = $otherEntity;
						});

						// TODO CALLBACKS

						$modelContainsModels = array_keys($modelContains->contains);
						unset($modelContainsModels[static::class]); // todo zpetna se bude resit jinde

						// Projdeme modelovo other entity
						foreach (EntityHelper::getPropertiesOfOtherEntities($relatedProperty->relatedColumnProperty->entityClass, array_keys($modelContainsModels)) as $relatedEntityRelatedProperty) {
							if ($relatedEntityRelatedProperty->columnProperty->column === $relatedColumn) {
								// Pokud je spojovací sloupec stejny, uz zname hodnotu a můžeme přidat conditions
								// todo nevyzk.
								bdump("todo", "Zkouška");
								$relatedModelContains = $modelContains->contains[$relatedEntityRelatedProperty->relatedColumnProperty->entityClass::getModelClass()];
								$relatedModelContains->params->getConditions()->addOrCondition($relatedEntityRelatedProperty->relatedColumnProperty->column, $value);
							}
						}
					} else {
						$relatedProperty->property->setValue($entity, []);
					}
				}
			} else {
				// Relace ->
				foreach ($entities as $entity) {
					if ($relatedProperty->property->isInitialized($entity)) {
						continue;
					}

					if (isset($entity->{$keyPropertyName})) {
						// Spojovací klíč má hodnotu // todo teoreticky datum, takze spis hodnotu sloupce nejak
						$value = $entity->{$keyPropertyName};
						// Přidáme do conditions
						$modelContains->params->getConditions()->addOrCondition($relatedColumn, $value);

						$findQuery->getEntityCache($modelContains)->setOnAdd($relatedColumn, $value, true, function (?CakeEntity $otherEntity) use ($relatedProperty, $entity) {
							if ($otherEntity === null && $relatedProperty->property->getType()->allowsNull()) {
								$relatedProperty->property->setValue($entity, null);
							} elseif ($otherEntity) {
								$relatedProperty->property->setValue($entity, $otherEntity);
							}
							// Zůstane unset, pokud není nullable a append je null. Může se pak asi točit - performance?
						});

						// TODO CALLBACKS

						$modelContainsModels = $modelContains->contains;
						/*bdump($modelContainsModels, static::class . ' ->' . $modelClass);
						if ($modelClass === 'AuthorTest') {
							bdump($modelContainsModels[static::class]);
							$prop = EntityHelper::getPropertiesOfOtherEntities($relatedProperty->relatedColumnProperty->entityClass, [static::class]);
							$relatedEntityRelatedProperty = current($prop);
							bdump($prop);
							bdump($relatedColumn);
							bdump($relatedEntityRelatedProperty->columnProperty->column);
							$relatedModelContains = $modelContains->contains[$relatedEntityRelatedProperty->relatedColumnProperty->entityClass::getModelClass()];
							bdump($relatedModelContains);
						}*/
						unset($modelContainsModels[static::class]); // todo zpetna se bude resit jinde
						// Projdeme modelovo other entity
						foreach (EntityHelper::getPropertiesOfOtherEntities($relatedProperty->relatedColumnProperty->entityClass, array_keys($modelContainsModels)) as $relatedEntityRelatedProperty) {
							//bdump($relatedEntityRelatedProperty, $modelClass);
							if ($relatedEntityRelatedProperty->columnProperty->column === $relatedColumn) {
								// Pokud je spojovací sloupec stejny, uz zname hodnotu a můžeme přidat conditions
								$relatedModelContains = $modelContains->contains[$relatedEntityRelatedProperty->relatedColumnProperty->entityClass::getModelClass()];
								$relatedModelContains->params->getConditions()->addOrCondition($relatedEntityRelatedProperty->relatedColumnProperty->column, $value);
							}
						}
					} else {
						$relatedProperty->property->setValue($entity, null);
					}
				}
			}
		}



    }


    private function findModelsInConditions(array &$models, array $conditions): array
    {
        $found = [];
        foreach ($conditions as $key => $value) {
            if (is_array($value)) {
                foreach ($models as $modelKey => $model) {
                    if (strpos($key, $model) !== false) {
                        $found[] = $model;
                        unset($models[$modelKey]);
                    }
                }
                $found = array_merge($found, $this->findModelsInConditions($models, $value));
            } else {
                foreach ($models as $modelKey => $model) {
                    if (strpos($key . $value, $model) !== false) {
                        $found[] = $model;
                        unset($models[$modelKey]);
                    }
                }
            }
        }
        return $found;
    }

    private function getJoins(array $contains, \AppModel $model): array
    {
        $joins = [];
        foreach ($contains as $childModelName => $childSettings) {
            $childModel = $this->getModel($childModelName);
            $childAlias = $childModel->alias;
            $join = [
                'table' => $childModel->useTable,
                'alias' => $childModel->alias,
            ];
            if (isset($childSettings['foreignKey'])) {
                $on = "$childAlias.{$childSettings['foreignKey']} = $model->alias.$model->primaryKey";
                $join['conditions'] = [$on];
            } elseif (isset($childSettings['key'])) {
                $on = "$childAlias.$childModel->primaryKey = $model->alias.{$childSettings['key']}";
                $join['conditions'] = [$on];
            } else {
                continue;
            }
            $join['type'] = 'LEFT';

            $joins[$childModelName][$on] = $join;
            if (isset($childSettings['contains'])) {
                $childJoins = $this->getJoins($childSettings['contains'], $childModel);
                foreach ($childJoins as &$childJoin) {
                    $childJoin = array_merge($joins[$childModelName], $childJoin);
                }
                $joins = array_merge($joins, $childJoins);
            }
        }
        return $joins;
    }

}