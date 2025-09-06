<?php

namespace Cesys\CakeEntities\Model;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Cesys\CakeEntities\Model\Entities\ColumnProperty;
use Cesys\CakeEntities\Model\Entities\EntityHelper;
use Cesys\CakeEntities\Model\Entities\RelatedProperty;
use Cesys\CakeEntities\Model\Find\Cache;
use Cesys\CakeEntities\Model\Find\EntityCache;
use Cesys\CakeEntities\Model\Find\FindConditions;
use Cesys\CakeEntities\Model\Find\CakeParams;
use Cesys\CakeEntities\Model\Find\FindParams;
use Cesys\CakeEntities\Model\Find\ModelCache;
use Cesys\CakeEntities\Model\LazyModel\ModelLazyModelTrait;
use Cesys\CakeEntities\Model\Recursion\FindQuery;
use Cesys\CakeEntities\Model\Recursion\Query;
use Cesys\CakeEntities\Model\Recursion\Timer;
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

	private ModelCache $modelCache;

	/**
	 * @var FindQuery[]
	 */
	private static array $findQueries = [];


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
			$query->onEnd[] = function () use (&$query, $resetTemporaryContains) {
				if ($resetTemporaryContains) {
					foreach (array_unique($query->path) as $modelClass) {
						/** @var static $Model */
						$Model = $this->getModel($modelClass);
						$Model->setTemporaryContains();
					}
				}
				$query = null;

			};
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
				$contains = [static::class => ['contains' => $contains]];
			}
            return $contains;
        }

        $containedModels = array_keys($contains);

		$relatedProperties = array_merge(
			EntityHelper::getPropertiesOfReferencedEntities(static::getEntityClass()),
			EntityHelper::getPropertiesOfRelatedEntities(static::getEntityClass())
		);

		// Odebereme z $contains všechny klíče (= modely), co nejsou v $relatedProperties todo throw?
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
			if (isset($contains[$modelClass]) && ! array_key_exists('contains', $contains[$modelClass])) {
				// Pole existuje, ale nemá prvek 'contains' => contains je defaultně []
				$contains[$modelClass]['contains'] = [];
			}

			$contains[$modelClass]['contains'] = $Model->getFullContains($contains[$modelClass]['contains'] ?? null);
        }



		if ($query->end()) {
			//bdump($query->path);
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
     * @param string|int $id
     * @param array|null $contains
     * @param bool $useCache
     * @return ?E
     */
    public function getEntity($id, ?array $contains = null, bool $useCache = true)
    {
        $entities = $this->getEntities([$id], $contains, $useCache);
        return $entities ? current($entities) : null;
    }



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

	private function createFindQuery(array $fullContains, bool $useCache, ?CakeParams $originalParams = null): FindQuery
	{
		$findQuery = new FindQuery($fullContains, $useCache, $originalParams);
		self::$findQueries[] = $findQuery;
		return $findQuery;
	}

	private function buildSqlSubquery(): string
	{
		// $this->getFindQuery()->getFullContains()->params->toArray();
		$params = (CakeParams::create())->toArray();
		$params['conditions'] = $this->getFindQuery()->getFindParams()->getConditions()->toArray();
		$params['table'] = $this->getDataSource()->fullTableName($this);
		$params['alias'] = 'inner_' . $this->getDataSource()->fullTableName($this, false);
		$params['fields'] = [$this->primaryKey];
		$sql = trim($this->getDataSource()->buildStatement($params, $this));
		//bdump($sql);
		return$sql;
	}


	public function getModelCache(): ModelCache
	{
		return $this->modelCache ??= new ModelCache(static::class);
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
			Timer::start('$$FindStart$$');
			// Nové volání, inicializace FindQuery
			// Musíme získat aktuální findParams (contains plně normalizované a kompletní na dané nastavení)
		//bdump($contains, 'originalContains');
			$findParams = $this->getFullContains($contains, true); // Zde se resetuje
		//bdump($findParams, 'findParams');
			// static::findEntities může být voláno přímo uživatelem, rekurzí, ze static::findEntity a ze static::getEntities
			// Nové volání (není $findQuery tj. zde) je jenom buď přímo uživatelem, ze static::findEntity (tj. = taky uživatelem), nebo ze static::getEntities
			// Pokud je voláno ze static::getEntities tak víme, že $params jsou sytémové (očekávaného tvaru a conditions)
			// Tj volání ze static::getEntities je systémové, uživatelovo není
			if (debug_backtrace(! DEBUG_BACKTRACE_PROVIDE_OBJECT|DEBUG_BACKTRACE_IGNORE_ARGS,2)[1]['function'] !== 'getEntities') {
				$originalParams = CakeParams::create($params);
				$originalParams->setRecursive();
			}
			$findQuery = $this->createFindQuery($findParams, $useCache, $originalParams ?? null);
			//\Tracy\Debugger::timer('test');
			//bdump($findQuery, "Find Query spl: " . spl_object_id($findQuery));
		}
		// Zapíšeme toto volání do FindQuery
		$findQuery->findStart(static::class);

		// Spočítané FindParams pro tento model, resp. pro tento model v tomto místě původního celkového FindParams
		$findParams = $findQuery->getFindParams();

		if ($findQuery->isFirstModelCall()) {
			$findQuery->onEnd[] = function () use ($findQuery, $findParams, $useCache) {
				// Všechny při findu vytvořené EntityCache nahrajeme do modelovo ModelCache
				$this->getModelCache()->mergeCache($findQuery->cache->getModelCache(static::class));
				if ($findParams->willBeUsed !== 0) {
					// Debug kontrola
					bdump($findParams, 'Contains with bad used nodes ' . $findParams->modelClass);
				}
			};
		}

		$entityCache = $findQuery->getEntityCache();

		if ($findQuery->isOriginalCall() && $findQuery->isSystemCall()) {
			// FindConditions nejsou vytvořeny jedině v případě původního volání findEntities,
			// které je systémové, takže vložíme conditions do FindConditions
			$findParams->setConditions(FindConditions::create($params['conditions']));
		}

		if ( ! $findParams->getConditions()->stampOrConditions() && ! $findParams->getConditions()->hasStringConditions()) {
			// Stejné FindOrConditions už byly a není volání recursive
			$findParams->afterFind();
			$findParams->getConditions()->clear();
			$findQuery->findEnd();
			return [];
		}

		// Pro systémová volání zde projdeme OR conditions - sloupce a jejich hodnoty
		// Pokud pro danou hodnotu sloupce ještě není index, je vytvořen = inicializace této hodnoty v cache jako [] => To je nutné, žádné entity ve výsledku nemusí existovat...
		// Pokud už naopak existuje, je daná hodnota odebrána z or conditions = víme, že tato hodnota je už v cache
		$preparedIndexes = [];
		$cachedEntities = [];
		if ($findQuery->isSystemCall() && ! $findParams->getConditions()->getOr()->isEmpty()) {
			foreach ($findParams->getConditions()->getOr()->toArray() as $column => $values) {
				$indexEmpty = false;
				$preparedIndexes[$column] = $column;
				foreach ($values as $value) {
					if ( ! $entityCache->startIndexValue($column, $value)) { // Pokud na daném sloupci ještě není index, vytvoří se
						// Index pro danou hodnotu sloupce už existuje v cache -> odebereme z conditions
						$cachedEntities += $entityCache->getEntities($value, $column);
						$indexEmpty = $findParams->getConditions()->removeOrCondition($column, $value);
					}
				}
				// Mohlo dojít k tomu, že všechny hodnoty sloupce jsou již v cache -> musíme odebrat i tento sloupec
				if ($indexEmpty) {
					unset($preparedIndexes[$column]);
					$findParams->removeNextUsedIndex($column);
				}
			}
		}


		// Uložíme si, jestli je toto volání so vl. tabulky s cílem doplnit vlastní recursive entity po 1. nesystémovém volání
		$isInSelfAfterNonSystem = false;
		// Pro systémová volání s nekonečnou rekurzí do vlastní tabulky
		if (
			$findQuery->containsSameModel() // Je definována rekurze do vlastní tabulky
			&& $findQuery->isSystemCall()  // Pokud jsou uživatelovy params, nemůžeme zasahovat
			&& ! $findParams->getConditions()->isEmpty() // Bez podmínek nemá smysl -> výsledek je 0 entit
			&& $findQuery->isRecursiveToSelfEndlessCacheCompatible() // Můsí být stejné, tj. jde o nekonečnou rekurzi se stejnými params todo speed uložit
		) {
			if ($findParams->getConditions()->hasStringConditions()) {
				// stringConditions vytváří framework v jediném případě - že se ptá na rekurzi do vlastní tabulky
				// Tedy pokud jsou už definovány, je toto samotné volání findEntites kvůli získání záznamů ze stejné tabulky, do něj zde zasahovat nechceme
				// K čemuž dojde jen u prvního nesystémového volání s nekonečnou rekurzí do vlastní tabulky
				// Ale uložíme si, pro pozdější použití
				if ($findParams === $findParams->contains[static::class]) {
					$isInSelfAfterNonSystem = true;
				}
			} else {
				$this->addSameEntitiesRecursive([],true);
			}
		}

	// "Hlavní" část -> jediné volání \AppModel::find
		$entities = [];
		//bdump($findQuery, 'BEFOREFIND - ' . static::class);
		if ( ! $findQuery->isSystemCall() || ! $findParams->getConditions()->isEmpty()) {
			// Uživatelovo volání, nebo když je neco v conditions
			if ( ! $findQuery->isSystemCall()) {
				// FindConditions jsou při nesystémovém volání vždy zde prázdné => jen uživatelovo params
				$params = $findQuery->getOriginalParams()->toArray();
			} else {
				$params = $findParams->containsParams->toArray();
				$params['conditions'][] = $findParams->getConditions()->toArray();
			}
			// Fields jsou vždy všechny co jsou na entitě, jinak psycho
			$params['fields'] = $this->getFields();
			$entitiesData = $this->find('all', $params);
			foreach ($entitiesData as $entityData) {
				$primary = $entityData[$this->alias][$this->primaryKey];
				if ($entity = $entityCache->getEntity($primary)) {
					// Pokud je fetchnuta znova entita, která už je v cache, nepřepisuje se!
				} else {
					$entity = $this->createEntity($entityData);
				}

				$entities[$entity->getPrimary()] = $entity;
			}
		}
		$entityCache->addAfterFind($entities, $findParams->getNextUsedIndexes(), $preparedIndexes);

		//
		$entities = $entities + $cachedEntities;

		// Počítadlo, uložené indexy -> reset
		$findParams->afterFind();
		// Reset conditions
		$findParams->getConditions()->clear();

		if ($isInSelfAfterNonSystem) {
			// Jsme v rekurzi do vlastní tabulky se stejnými FindParams hned po 1. nesystémovém volání
			// Dále jít nechceme, protože co se děje dále chceme až se všema entitama v předchozím volání,
			// které budou v případě identické FindParams sloučeny
			$findQuery->findEnd();
			return [];
		}

		$entitiesToAppendOthers = $entities;

		if ($findQuery->containsSameModel()) {
			// Řešíme rekurzi do vlastní tabulky
			$relatedFindParams = $findParams->contains[static::class];
			if ($findQuery->isRecursiveToSelfEndlessCacheCompatible()) {
				// Jde o nekonečnou rekurzi s podobnou cache
				if ( ! $findQuery->isSystemCall()) {
					// První volání, kde se před find nedoplňují RECURSIVE && nekonečné contains => chceme RECURSIVE
					$this->addSameEntitiesRecursive($entities);
					// Tj. toto je vždy jediný případ, kdy to může nastat -> druhé volání po prvním nesystémovém callu s nekonečnou rekurzí do vl. tabulky
					$this->findEntities([], [], $useCache);
					// Přiřadily se entity do vl. tabulky, dál se jim ale nic nepřiřadilo
					// Dále v kódu chceme připojit ke všem entitám, ne jen těm z prvního findu
					// Nicméně isRecursiveToSelfEndlessCacheCompatible neznamená, že budou připojovat **všechny** z contains,
					// takže sloučit můžeme jen v případě totožnosti
					//
					if ($findParams === $relatedFindParams) {
						$entitiesToAppendOthers = $findQuery->getEntityCache()->getEntitiesByPrimary();
					}
				}
			} else {
				// Jsou konečné FindParams, nebo nekompatibilní pro RECURSIVE, pouze flat
				$this->addSameEntities($entities);
				$this->findEntities([], [], $useCache);
			}
		}


        // Připojení entit z ostatních tabulek
        $this->addOtherEntities($entitiesToAppendOthers);


		// Findy other modelů
		foreach ($findParams->contains as $modelClass => $modelContains) {
			if (static::class === $modelClass) {
				// Vyřešeno výše
				continue;
			}
			$isInFindParamsRecursion = $findQuery->isChildModelInFindParamsRecursion($modelClass);
			if ($modelContains->hasNextStandardFind($isInFindParamsRecursion)) {
				// Přeskočení, tato 'větev' FindParams se objevuje ještě minimálně 1x ve stromu původních FindParams, tj. ještě budeme
				// určitě volat v rámci tohoto celkového findu. Můžeme přeskočit, FindConditions pro tuto část větve již byly připojeny
				if ( ! $isInFindParamsRecursion) {
					// Jedná se o nevyslání do findu, které ale bylo v nerekurzivních FindParams, a tudíž připočteno do použití
					// Volá se skipUse, protože chceme jen snížit 'willBeUsed', ale ne smazat nastavené indexy
					$modelContains->skipUse();
				}
				continue;
			}
			/** @var static $Model */
			$Model = $this->getModel($modelClass);
			$Model->findEntities([], [], $useCache);
		}


        if ($findQuery->findEnd()) {
			//bdump($this->getModelCache());
			array_pop(self::$findQueries);
			Timer::stop();
			Timer::getResults();
			bdump($findQuery, static::class);
			//bdump($entities);
			// Debug
			$checkedEntities = [];
			$paramsQueue = new \SplQueue();
			$paramsQueue[] = [$findQuery->findParams, $entities];
			foreach ($paramsQueue as [$findParams, $exEntities]) {
				$entityClass = $findParams->modelClass::getEntityClass();
				$relatedProperties = EntityHelper::getPropertiesOfOtherEntities($entityClass, array_keys($findParams->contains));
				if (isset($findParams->contains[$findParams->modelClass])) {
					$relatedProperties += EntityHelper::getPropertiesOfSelfEntities($entityClass);
				}
				$allChildEntities = [];
				foreach ($relatedProperties as $relatedProperty) {
					$modelClass = $relatedProperty->relatedColumnProperty->entityClass::getModelClass();
					$childEntities = [];
					foreach ($exEntities as $entity) {
						if (isset($checkedEntities[spl_object_id($entity)])) {
							continue;
						}
						if ( ! $relatedProperty->property->isInitialized($entity)) {
							bdump($entity, '!!!Uninitialized property ' . get_class($entity) . '::' . $relatedProperty->property->getName());
						} else {
							if ($relatedProperty->property->getType()->getName() === 'array') {
								$childEntities += $relatedProperty->property->getValue($entity);
							} else {
								if ($childEntity = $relatedProperty->property->getValue($entity)) {
									$childEntities += [$childEntity->getPrimary() => $childEntity];
								}
							}
						}
					}
					if ($childEntities) {
						$allChildEntities[$modelClass] = ($allChildEntities[$modelClass] ?? []) + $childEntities;
					}
				}
				foreach ($exEntities as $entity) {
					$checkedEntities[spl_object_id($entity)] = true;
				}
				foreach ($findParams->contains as $modelClass => $childFindParams) {
					if ( ! isset($allChildEntities[$modelClass])) {
						continue;
					}
					$paramsQueue[] = [$childFindParams, $allChildEntities[$modelClass]];
				}
			}
			//
			//bdump($entities, "FINAL FIND ENTITIES");
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
		// conditions přesně tak, jak ho přidává static::findEntities => Bude zpracován jako system call
        $entities = $this->findEntities([
            'conditions' => [
                'OR' => [
                    $this->primaryKey => $ids
                ]
            ]
        ], $contains, $useCache);

        $result = [];
        foreach ($ids as $id) {
            if (isset($entities[$id])) {
                $result[$id] = $entities[$id];
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
                $entity->setPrimary($this->id); // todo spis z reziult => merge 4288
            }

            // Todo mozna priradit i zbytek co se vratilo, ale zatím není jistý, pokud chceme defaulty, meli bychom entitu spravne vytvaret
            if (isset($result[$this->alias]['created']) && ($columnProperty = EntityHelper::getColumnProperties(static::getEntityClass())['created'] ?? null)) {
				EntityHelper::appendFromDbValue($entity, $columnProperty, $result[$this->alias]['created']);
            }
            if (isset($result[$this->alias]['modified'])  && ($columnProperty = EntityHelper::getColumnProperties(static::getEntityClass())['modified'] ?? null)) {
				EntityHelper::appendFromDbValue($entity, $columnProperty, $result[$this->alias]['modified']);
            }
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
	 * @deprecated
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
		if (isset($this->fields)) {
			return $this->fields;
		}

		$this->fields = [];
		foreach (EntityHelper::getColumnProperties(static::getEntityClass()) as $columnProperty) {
			$this->fields[] = $columnProperty->column;
		}

		return $this->fields;
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


	/**
	 * Setuje defaultně [] / null (null jen pokud mozno)
	 * @param array $entities
	 * @return void
	 */
	private function addSameEntities(array $entities): void
	{
		$findQuery = $this->getFindQuery();
		if ( ! $entities) {
			return;
		}

		// Najdeme modelovo contains
		$modelFindParams = $findQuery->getFindParams([static::class]);

		foreach (EntityHelper::getPropertiesOfSelfEntities(static::getEntityClass()) as $relatedProperty) {
			$keyPropertyName = $relatedProperty->columnProperty->propertyName;
			// Vazební sloupec
			$relatedColumn = $relatedProperty->relatedColumnProperty->column;

			if ($relatedProperty->property->getType()->getName() === 'array') {
				// Relace []
				foreach ($entities as $entity) {
					$isInitialized =  $relatedProperty->property->isInitialized($entity);
					if ( ! $isInitialized) {
						$relatedProperty->property->setValue($entity, []);
					}
					if (isset($entity->{$keyPropertyName})) {
						$modelFindParams->setNextUsedIndex($relatedColumn);
						// Spojovací klíč má hodnotu
						$value = EntityHelper::getPropertyDbValue($entity, $relatedProperty->columnProperty);
						// Přidáme do conditions
						$modelFindParams->getConditions()->addOrCondition($relatedColumn, $value);

						if ( ! $isInitialized) {
							$findQuery->getEntityCache($modelFindParams)->setOnAdd($relatedProperty, $value, function (CakeEntity $otherEntity) use ($relatedProperty, $entity) {
								$entity->{$relatedProperty->property->getName()}[$otherEntity->getPrimary()] = $otherEntity;
							});
						}
					}
				}
			} else {
				// Relace ->
				foreach ($entities as $entity) {
					$isInitialized =  $relatedProperty->property->isInitialized($entity);
					if ( ! $isInitialized && $relatedProperty->property->getType()->allowsNull()) {
						$relatedProperty->property->setValue($entity, null);
					}
					if (isset($entity->{$keyPropertyName})) {
						$modelFindParams->setNextUsedIndex($relatedColumn);
						// Spojovací klíč má hodnotu
						$value = EntityHelper::getPropertyDbValue($entity, $relatedProperty->columnProperty);
						// Přidáme do conditions
						$modelFindParams->getConditions()->addOrCondition($relatedColumn, $value);

						if ( ! $isInitialized) {
							$findQuery->getEntityCache($modelFindParams)->setOnAdd($relatedProperty, $value, function (CakeEntity $otherEntity) use ($relatedProperty, $entity) {
								$relatedProperty->property->setValue($entity, $otherEntity);
								// Zůstane unset, pokud není nullable a append je null. Může se pak asi točit - performance?
							});
						}
					}
				}
			}
		}
	}


	/**
	 * Před voláním musí být ověřeno, že je ve FindParams rekurze do vlastní tabulky
	 * Toto FindParams musí být stejné, jako původní FindParams, tj. nekonečná rekurze do vlastní tabulky se stejnými Params
	 * Před voláním při $fromSubQuery = true musí být ověřeno, že FindConditions nejsou prázdné
	 * @param E[] $entities
	 * @param bool $fromSubQuery
	 * @return void
	 */
    private function addSameEntitiesRecursive(array $entities, bool $fromSubQuery = false): void
    {
		$findQuery = $this->getFindQuery();
		if ($fromSubQuery) {
			$entities = [];
		} elseif ( ! $entities) {
            return;
        }

		$findParams = $fromSubQuery ? $findQuery->getFindParams() : $findQuery->getFindParams([static::class]);
		$entityCache = $findQuery->getEntityCache($findParams);

        $selects = [
            'ascendants' => [],
            'descendants' => [],
        ];
        $idsToFetch = [];
		$nextUsedRelatedProperties = [];
        foreach (EntityHelper::getPropertiesOfSelfEntities(static::getEntityClass()) as $relatedProperty) {
			$relatedColumn = $relatedProperty->relatedColumnProperty->column;
			$keyPropertyName = $relatedProperty->columnProperty->propertyName;
			$nextUsedRelatedProperties[] = $relatedProperty;
            foreach ($entities as $entity) {
				if ($entityCache->isRecursiveAppended($entity)) {
					continue;
				}
				if ( ! isset($entity->{$keyPropertyName})) {
					continue;
				}
				$idsToFetch[] = $entity->getPrimary();
            }
			if ($relatedProperty->property->getType()->getName() === 'array') {
				$selects['descendants'][] = [$relatedColumn, $relatedProperty->columnProperty->column];
			} else {
				$selects['ascendants'][] = [$relatedProperty->columnProperty->column, $relatedColumn];
			}
        }

		if ( ! $fromSubQuery) {
			$idsToFetch = $this->filterIds($idsToFetch);
			if ( ! $idsToFetch) {
				return;
			}
		}

		foreach ($nextUsedRelatedProperties as $nextUsedRelatedProperty) {
			// Index je vždy na related column
			$relatedColumn = $nextUsedRelatedProperty->relatedColumnProperty->column;
			$findParams->setNextUsedIndex($relatedColumn);
			$entityCache->setOnNextAddAppendRelatedProperties($nextUsedRelatedProperty);
		}

		$isSimple = (count($selects['ascendants']) + count($selects['descendants'])) === 1;

		$ascendantsBindingColumns = array_unique(Arrays::flatten($selects['ascendants']));
		$descendantsBindingColumns = array_unique(Arrays::flatten($selects['descendants']));
		$allBindingColumns = array_unique(array_merge($ascendantsBindingColumns, $descendantsBindingColumns));
		$ascendantsTBindingColumns = array_map(fn($column) => "t.$column", $ascendantsBindingColumns);
		$descendantsTBindingColumns = array_map(fn($column) => "t.$column", $descendantsBindingColumns);
		$ascendantsBindingColumns = implode(', ', $ascendantsBindingColumns);
		$descendantsBindingColumns = implode(', ', $descendantsBindingColumns);
		$ascendantsTBindingColumns = implode(', ', $ascendantsTBindingColumns);
		$descendantsTBindingColumns = implode(', ', $descendantsTBindingColumns);
		$ascendantsOns = [];
		$descendantsOns = [];
		foreach ($selects['ascendants'] as [$column, $bindingColumn]) {
			$rightAlias = $isSimple ? 'ascendants' : 'recursive_relations';
			$ascendantsOns[] = "t.$bindingColumn = $rightAlias.$column";
		}
		foreach ($selects['descendants'] as [$bindingColumn, $column]) {
			$rightAlias = $isSimple ? 'descendants' : 'recursive_relations';
			$descendantsOns[] = "t.$bindingColumn = $rightAlias.$column";
		}

		$sql = 'WITH RECURSIVE';
		$sqlEnd = '';
		$subquery = null;
		if ($fromSubQuery) {
			$orConditions = $findParams->getConditions()->getOr()->toArray();
			if (count($orConditions) === 1 && isset($orConditions[$this->primaryKey])) {
				$idsToFetch = $orConditions[$this->primaryKey];
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

		if ($isSimple) {
			// "starý" způsob, pouze 1 spoj a jedním směrem
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
		} else {
			// Nový způsob, univerzální, i když v rozsáhlé spadne nebo se podělá, závisí na velikosti CHAR(65536) todo
			$allBindingTColumns = array_map(fn($column) => "t.$column", $allBindingColumns);
			$allBindingColumns = implode(', ', $allBindingColumns);
			$allBindingTColumns = implode(', ', $allBindingTColumns);
			$sql .= "
				recursive_relations AS (
					SELECT $allBindingColumns, CAST($this->primaryKey AS CHAR(65536)) AS path
					FROM $this->useTable
					WHERE $where
					UNION ALL
				";
			if ($ascendantsOns) {
				$ascendantsOnsText = implode(' OR ', $ascendantsOns);
				$sql .= "
					SELECT $allBindingTColumns, CONCAT(path, ',', t.$this->primaryKey) AS path
					FROM $this->useTable t
					INNER JOIN recursive_relations ON $ascendantsOnsText
					WHERE FIND_IN_SET(t.$this->primaryKey, path) = 0 
				";
			}
			if ($descendantsOns) {
				if ($ascendantsOns) {
					$sql .= "\nUNION ALL\n";
				}
				$descendantsOnsText = implode(' OR ', $descendantsOns);
				$sql .= "
					SELECT $allBindingTColumns, CONCAT(path, ',', t.$this->primaryKey) AS path
					FROM $this->useTable t
					INNER JOIN recursive_relations ON $descendantsOnsText
					WHERE FIND_IN_SET(t.$this->primaryKey, path) = 0 
				";
			}
			$sql .= "
				)
				SELECT DISTINCT id
				FROM recursive_relations
			";
		}
		$sql .= $sqlEnd;

		if ($fromSubQuery) {
			// Odebírá se, protože conditions se přepsaly do subQuery a jediná část dotazu, ve které je vše, se přidá následně do stringConditions
			$findParams->getConditions()->clear();
			$findParams->getConditions()->stringConditions[] = "$this->primaryKey IN ($sql)";
			return;
		}
		// FindConditions jsou tady prázdné (hodnoty se braly z entit)
		$findParams->getConditions()->stringConditions[] = "$this->primaryKey IN ($sql)";
    }


    /**
	 * Setuje defaultně [] / null (null jen pokud mozno)
     * @param E[] $entities
     * @param ?array $contains
     * @param bool $useCache
     */
    private function addOtherEntities(array $entities): void
    {
        if ( ! $entities) {
            return;
        }

        $findQuery = $this->getFindQuery();
		$findParams = $findQuery->getFindParams();

        if ( ! $findParams->contains) {
            return;
        }

        $containedModels = array_keys($findParams->contains);

		foreach (EntityHelper::getPropertiesOfOtherEntities(static::getEntityClass(), $containedModels) as $relatedProperty) {
			$modelClass = $relatedProperty->relatedColumnProperty->entityClass::getModelClass();

			$keyPropertyName = $relatedProperty->columnProperty->propertyName;
			// Vazební sloupec v cizí tabulce
			$relatedColumn = $relatedProperty->relatedColumnProperty->column;
			// Najdeme modelovo FindParams
			$modelFindParams = $findQuery->getFindParams([$modelClass]);

			if ($relatedProperty->property->getType()->getName() === 'array') {
				// Relace []
				foreach ($entities as $entity) {
					$isInitialized =  $relatedProperty->property->isInitialized($entity);
					if ( ! $isInitialized) {
						$relatedProperty->property->setValue($entity, []);
					}
					if (isset($entity->{$keyPropertyName})) {
						$modelFindParams->setNextUsedIndex($relatedColumn);
						// Spojovací klíč má hodnotu
						$value = EntityHelper::getPropertyDbValue($entity, $relatedProperty->columnProperty);
						// Přidáme do conditions
						$modelFindParams->getConditions()->addOrCondition($relatedColumn, $value);

						if ( ! $isInitialized) {
							$findQuery->getEntityCache($modelFindParams)->setOnAdd($relatedProperty, $value, function (CakeEntity $otherEntity) use ($relatedProperty, $entity) {
								$entity->{$relatedProperty->property->getName()}[$otherEntity->getPrimary()] = $otherEntity;
							});
						}
					}
				}
			} else {
				// Relace ->
				foreach ($entities as $entity) {
					$isInitialized =  $relatedProperty->property->isInitialized($entity);
					if ( ! $isInitialized && $relatedProperty->property->getType()->allowsNull()) {
						$relatedProperty->property->setValue($entity, null);
					}
					if (isset($entity->{$keyPropertyName})) {
						$modelFindParams->setNextUsedIndex($relatedColumn);
						// Spojovací klíč má hodnotu
						$value = EntityHelper::getPropertyDbValue($entity, $relatedProperty->columnProperty);
						// Přidáme do conditions
						$modelFindParams->getConditions()->addOrCondition($relatedColumn, $value);

						if ( ! $isInitialized) {
							$findQuery->getEntityCache($modelFindParams)->setOnAdd($relatedProperty, $value, function (CakeEntity $otherEntity) use ($relatedProperty, $entity) {
								$relatedProperty->property->setValue($entity, $otherEntity);
							});
						}
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