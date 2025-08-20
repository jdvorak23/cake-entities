<?php

namespace Cesys\CakeEntities\Model;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Cesys\CakeEntities\Model\Entities\ColumnProperty;
use Cesys\CakeEntities\Model\Entities\EntityHelper;
use Cesys\CakeEntities\Model\Entities\RelatedProperty;
use Cesys\CakeEntities\Model\Find\FindConditions;
use Cesys\CakeEntities\Model\Find\Params;
use Cesys\CakeEntities\Model\Find\Stash;
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
		// $this->getFindQuery()->getFullContains()->params->toArray();
		$params = (Params::create())->toArray();
		$params['conditions'] = $this->getFindQuery()->getFullContains()->getConditions()->toArray();
		$params['table'] = $this->getDataSource()->fullTableName($this);
		$params['alias'] = 'inner_' . $this->getDataSource()->fullTableName($this, false);
		$params['fields'] = [$this->primaryKey];
		$sql = trim($this->getDataSource()->buildStatement($params, $this));
		//bdump($sql);
		return$sql;
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
			// Musíme získat aktuální fullContains (contains plně normalizované a kompletní na dané nastavení)
			bdump($contains, 'originalContains');
			$fullContains = $this->getFullContains($contains, true); // Zde se resetuje
			bdump($fullContains, 'fullContains');
			// static::findEntities může být voláno přímo uživatelem, rekurzí, ze static::findEntity a ze static::getEntities
			// Nové volání (není $findQuery tj. zde) je jenom buď přímo uživatelem, ze static::findEntity (tj. = taky uživatelem), nebo ze static::getEntities
			// Pokud je voláno ze static::getEntities tak víme, že $params jsou sytémové (očekávaného tvaru a conditions)
			// Tj volání ze static::getEntities je systémové, uživatelovo není
			$isSystem = debug_backtrace(! DEBUG_BACKTRACE_PROVIDE_OBJECT|DEBUG_BACKTRACE_IGNORE_ARGS,2)[1]['function'] === 'getEntities';
			$findQuery = $this->createFindQuery($fullContains, $isSystem);
		}
		// Zapíšeme toto volání do FindQuery
		$findQuery->findStart(static::class);
		// Spočítané Contains pro tento model, resp. pro tento model v tomto místě původního celkového fullContains
		$fullContains = $findQuery->getFullContains();


		if (count($findQuery->activePath) > 500) {
			// todo dočasné, pokud někde uděláme chybu, aby to neskončilo v nekonečné rekurzi
			bdump("DOPICICI");
			$findQuery->findEnd();
			return [];
		}


		if ($findQuery->isOriginalCall()) {
			// Params nejsou vytvořeny jedině v případě původního volání findEntities
			if ($findQuery->isSystemCall()) {
				// Při systémovém volání vložíme conditions rovnou do FindConditions, protože víme, jak to bude vypadate
				$fullContains->setConditions(FindConditions::create($params['conditions']));
			} else {
				$fullContains->params = Params::create($params);
			}
			// I kdyby uživatel nastavil něco jiného, je to -1
			$fullContains->params->setRecursive(-1);
		}
		// Fields jsou vždy všechny co jsou na entitě, jinak psycho
		$fullContains->params->setFields($this->getFields());


		// Pro systémová volání zde projdeme OR conditions - sloupce a jejich hodnoty
		// Pokud pro danou hodnotu sloupce ještě není index, je vytvořen = inicializace této hodnoty v cache jako [] => To je nutné, žádné entity ve výsledku nemusí existovat...
		// Pokud už naopak existuje, je daná hodnota odebrána z or conditions = víme, že tato hodnota je už v cache
		$preparedIndexes = [];
		if ($findQuery->isSystemCall() && ! $fullContains->getConditions()->getOr()->isEmpty()) {
			$entityCache = $findQuery->getEntityCache();
			foreach ($fullContains->getConditions()->getOr()->keyConditions as $column => $values) {
				$preparedIndexes[$column] = $column;
				foreach ($values as $key => $id) {
					if ( ! $entityCache->startIndexValue($column, $id)) { // Pokud na daném sloupci ještě není index, vytvoří se
						// Index pro danou hodnotu sloupce už existuje v cache -> odebereme z conditions
						unset($fullContains->getConditions()->getOr()->keyConditions[$column][$key]);
					}
				}
				// Mohlo dojít k tomu, že všechny hodnoty sloupce jsou již v cache -> musíme odebrat i tento sloupec
				if ( ! $fullContains->getConditions()->getOr()->keyConditions[$column]) {
					unset($fullContains->getConditions()->getOr()->keyConditions[$column]);
					unset($preparedIndexes[$column]);
					$fullContains->removeNextUsedIndex($column);
				}
			}
		}


		// Uložíme si, jestli je toto volání so vl. tabulky s cílem doplnit vlastní recursive entity po 1. nesystémovém volání
		$isInSelfAfterNonSystem = false;
		// Pro systémová volání s nekonečnou rekurzí do vlastní tabulky
		if (
			$findQuery->isRecursiveToSelf() // Je definována rekurze do vlastní tabulky
			&& $fullContains === $fullContains->contains[static::class] // Můsí být stejné, tj. jde o nekonečnou rekurzi se stejnými params
			&& $findQuery->isSystemCall() // Pokud jsou uživatelovy params, nemůžeme zasahovat
			&& ! $fullContains->getConditions()->isEmpty() // Bez podmínek nemá smysl -> výsledek je 0 entit
		) {
			if ($fullContains->getConditions()->stringConditions) {
				// stringConditions vytváří framework v jediném případě - že se ptá na rekurzi do vlastní tabulky
				// Tedy pokud jsou definovány, je toto samotné volání findEntites kvůli získání záznamů ze stejné tabulky, do něj zde zasahovat nechceme
				// K čemuž dojde jen u prvního nesystémového volání s nekonečnou rekurzí do vlastní tabulky
				// Ale uložíme si, pro pozdější použití
				$isInSelfAfterNonSystem = true;
			} else {
				$this->addSameEntitiesRecursive([],true);
			}
		}
		if (count($findQuery->path) === 110 /*&& count($findQuery->activePath) === 1*/) {
			bdump($findQuery, 'Test --------------->   ' . static::class);
			bdump($fullContains->contains);
			bdump($fullContains->getConditions()->isEmpty());
			exit;
		}
		// "Hlavní" část -> jediné volání \AppModel::find
		$entities = [];
		//bdump($findQuery, 'BEFOREFIND - ' . static::class);
		if ( ! $findQuery->isSystemCall() || ! $fullContains->getConditions()->isEmpty()) {
			if ( ! $findQuery->isSystemCall()) {
				// FindConditions jsou při nesystémovém volání vždy zde prázdné => jen uživ. params
				$params = $fullContains->params->toArray();
			} else { // TODO BIG JAKSVIŇ
				$params = $fullContains->params->toArray();
				$params['conditions'][] = $fullContains->getConditions()->toArray();
			}
			//bdump($params);
			// Uživatelovo volání, nebo když je neco v conditions
			$entitiesData = $this->find('all', $params);
			foreach ($entitiesData as $entityData) {
				$primary = $entityData[$this->alias][$this->primaryKey];
				if (isset($findQuery->getStash()->getCache()[$primary])) {
					// Pokud je fetchnuta znova entita, která už je v cache, nepřepisuje se!
					$entity = $findQuery->getStash()->getCache()[$primary];
				} else {
					$entity = $this->createEntity($entityData);
					$this->entities[$entity->getPrimary()] = $entity;
				}

				$entities[$entity->getPrimary()] = $entity;
				// Takto se ujistíme, že i když entita tam už je, že bude na všech indexech
				$findQuery->getEntityCache()->add($entity, $preparedIndexes, $fullContains->getNextUsedIndexes() ?: null);
			}
		}

		// Počítadlo, uložené indexy -> reset
		$fullContains->afterFind();



		if ($findQuery->isSystemCall() && $fullContains->getConditions()->stringConditions) {
			// Bylo volání WITH RECURSIVE
			// Přiřazení všeho
			$this->appendSameEntities();
			if ($isInSelfAfterNonSystem) {
				// Doplnili jsme entity a jsme v rekurzi do vlastní tabulky hned po 1. nesystémovém volání
				// Dále jít nechceme, protože co se děje dále chceme až se všema entitama v předchozím volání
				// Takže návrat
				$fullContains->getConditions()->clear();
				$findQuery->findEnd();
				return [];
			}
		}




		// Reset conditions
		$fullContains->getConditions()->clear();
		if ( ! $findQuery->isSystemCall()) {
			// nonSystem
			$fullContains->params->clear();
		}

		$allEntities = $entities;

		if ($findQuery->isRecursiveToSelf()) {
			// Řešíme rekurzi do vlastní tabulky
			$relatedContains = $fullContains->contains[static::class];
			if ($fullContains === $relatedContains) {
				// Jde o nekonečnou rekurzi se stejnými params
				if ( ! $findQuery->isSystemCall()) {
					// První volání, kde se před find nedoplňují RECURSIVE && nekonečné contains => chceme RECURSIVE
					$this->addSameEntitiesRecursive($entities);
					if ( ! $relatedContains->getConditions()->isEmpty()) {
						// Tj. toto je vždy jediný případ, kdy to může nastat -> druhé volání po prvním nesystémovém callu s nekonečnou rekurzí do vl. tabulky
						$this->findEntities([], [], $useCache);
						// Přiřadily se entity do vl. tabulky, dál se jim ale nic nepřiřadilo
						// Dále v kódu chceme připojit ke všem entitám, ne jen těm z prvního findu
						$allEntities = $findQuery->getEntityCache()->getEntitiesByPrimary();
					} else {
						$relatedContains->afterFind(); // todo wtf?? nebo obalit jako níže ale smysl?
					}
				}
			} else {
				// Jsou konečné contains, pouze flat
				$this->addSameEntities($entities);
				if ( ! $relatedContains->getConditions()->isEmpty()) {
					$this->findEntities([], [], $useCache);
				} else {
					if ( ! $findQuery->isChildModelInContainsRecursion(static::class)) {
						// Jedná se o nevyslání do findu, které ale bylo v nerekurzivních contains, a tudíž připočteno do použití
						// Musíme odečíst
						$relatedContains->afterFind();
					}

				}
			}
		}

        // Připojení entit z ostatních tabulek
        $this->addOtherEntities($allEntities, $useCache);
		//bdump($findQuery, 'afterAdded Other Ents ' . static::class);
		//bdump($fullContains->inNodesUsed . ' (' . implode(', ', $findQuery->activePath) . ')', "HUUURRRRAAAA" . static::class);



		//bdump($fullContains);
		foreach ($fullContains->contains as $modelClass => $modelContains) {
			if (static::class === $modelClass) {
				// Vyřešeno výše
				continue;
			}
			if ($modelContains->getConditions()->isEmpty()) {
				if ( ! $findQuery->isChildModelInContainsRecursion($modelClass)) {
					// Jedná se o nevyslání do findu, které ale bylo v nerekurzivních contains, a tudíž připočteno do použití
					// Musíme odečíst
					$modelContains->afterFind();
				}
				continue;
			}
			//bdump($modelContains->inNodesUsed . ' (' . implode(', ', $findQuery->activePath) . ')', 'Nodes  '. $modelClass);
			if ($modelContains->hasNextStandardFind()) {
				$modelContains->skipUse();
				//bdump($modelContains, "Přeskok! " . static::class);
				//bdump($findQuery->isChildModelInContainsRecursion($modelClass));
				continue;
			} elseif ($modelContains->hasNU1() && $findQuery->isChildModelInContainsRecursion($modelClass)) {
				// Pokud je příští volání v rekurzi, na začátku by mu bylo připsáno +1, takže by to bylo +2, tj. bude volán ještě jindy
				//bdump($modelContains, "Výskok!");
				continue;
			} elseif (
				(
					$modelContains->inNodesUsed === 0
					&& ! $findQuery->isChildModelInContainsRecursion($modelClass)
				)
				|| $modelContains->inNodesUsed < 0
			) {
				bdump($findQuery, 'COZEEEEEEEEEEEEEEEEEEEEEEEE - ' . $modelClass . ' - ' . $findQuery->isChildModelInContainsRecursion($modelClass));
			}
			/** @var static $Model */
			$Model = $this->getModel($modelClass);
			$Model->findEntities([], [], $useCache);
		}



        if ($findQuery->findEnd()) {
            bdump($findQuery, static::class);
			array_pop(self::$findQueries);
			//
			$usedContains = [];
			foreach ($findQuery->cache->getCache() as $modelClass => $cacheArray) {
				$stash = null;
				foreach($cacheArray as $id => $cache) {
					if ($id === 'stash') {
						$stash = $cache;
						continue;
					}
					$usedContains[$cache->contains->getId()] = $cache->contains;
				}
				bdump($stash);
				/** @var Stash $stash */
				/** @var CakeEntity $entity */
				foreach($stash->getCache() as $entity) {
					$relatedProperties = EntityHelper::getPropertiesOfReferencedEntities(get_class($entity)) + EntityHelper::getPropertiesOfRelatedEntities(get_class($entity));
					foreach ($relatedProperties as $relatedProperty) {
						if ( ! $relatedProperty->property->isInitialized($entity)) {
							bdump($entity, 'Uninitialized property ' . get_class($entity) . '::' . $relatedProperty->property->getName());
						}
					}
				}
			}
			foreach ($usedContains as $contains) {
				if ($contains->inNodesUsed !== 0) {
					bdump($contains, 'Contains with bad used nodes ' . $contains->modelClass);
				}
			}
			//

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
			//bdump('Setting index same but other contains: ' . $relatedColumn, $relatedProperty->relatedColumnProperty->entityClass::getModelClass());
			$modelContains->setNextUsedIndex($relatedColumn);
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
						$modelContains->getConditions()->addOrCondition($relatedColumn, $value);
						$findQuery->getEntityCache($modelContains)->setOnAdd($relatedColumn, $value, false, function (CakeEntity $otherEntity) use ($relatedProperty, $entity) {
							$entity->{$relatedProperty->property->getName()}[$otherEntity->getPrimary()] = $otherEntity;
						});
					}
					// todo append [] když nesetnuto?
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
						$modelContains->getConditions()->addOrCondition($relatedColumn, $value);

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

						//bdump("coj");
						//bdump($value, $relatedColumn);
						//bdump($modelContains->contains);

						foreach (EntityHelper::getPropertiesOfSelfEntities(static::getEntityClass()) as $relatedEntityRelatedProperty) {
							if ($relatedEntityRelatedProperty->columnProperty->column === $relatedColumn) {
								bdump($relatedEntityRelatedProperty, "ANO");
								$relatedModelContains = $modelContains->contains[static::class];
								$modelContains->getConditions()->addOrCondition($relatedEntityRelatedProperty->relatedColumnProperty->column, $value);
							} else {
								//bdump("NE");
							}
						}
						// todo append null když nesetnuto?
					}
				}
			}
		}
		//bdump($entities);
	}


	/**
	 * Před voláním musí být ověřeno, že je v Contains rekurze do vlastní tabulky
	 * Toto Contains musí být stejné, jako původní Contains, tj. nekonečná rekurze do vlastní tabulky se stejnými Params
	 * Před voláním musí být ověřeno, že Conditions nejsou prázdné
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

		$fullContains = $findQuery->getFullContains();

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

			$fullContains->setNextUsedIndex($relatedProperty->relatedColumnProperty->column);
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
			if (count($fullContains->getConditions()->getOr()->keyConditions) === 1 && isset($fullContains->getConditions()->getOr()->keyConditions[$this->primaryKey])) {
				$idsToFetch = $fullContains->getConditions()->getOr()->keyConditions[$this->primaryKey];
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
			// todo jak toto mazání a proč dole neni
			// Odebírá se, protože conditions se přepsaly do subQuery a jediná část dotazu, ve které je vše, se přidá následně do stringConditions
			$findQuery->getFullContains()->getConditions()->clear();
			$findQuery->getFullContains()->getConditions()->stringConditions[] = "$this->primaryKey IN ($sql)";
			return;
		}
	// todo tady asi není výmaz ?
		$findQuery->getFullContains([static::class])->getConditions()->stringConditions[] = "$this->primaryKey IN ($sql)";
    }

	private function appendSameEntities()
	{

		$findQuery = $this->getFindQuery();
		$entityCache = $findQuery->getEntityCache();
		if ( ! $entities = $entityCache->getEntitiesByPrimary()) {
			return;
		}

		foreach (EntityHelper::getPropertiesOfSelfEntities(static::getEntityClass()) as $relatedProperty) {
			$keyPropertyName = $relatedProperty->columnProperty->propertyName;
			$relatedColumn = $relatedProperty->relatedColumnProperty->column;
			/*if ($entityCache->addIndex($relatedColumn)) {
				// Musíme naindexovat, pokud spojujeme jinam nez na id
				$entityCache->indexEntities($relatedColumn);
			}*/
			foreach ($entities as $entity) {
				if ($relatedProperty->property->isInitialized($entity)) {
					continue;
				}

				if ( ! isset($entity->{$keyPropertyName})) {
					if ($relatedProperty->property->getType()->getName() === 'array') {
						// 1:M
						$relatedProperty->property->setValue($entity, []);
					} elseif ($relatedProperty->property->getType()->allowsNull()) {
						// 1:1
						$relatedProperty->property->setValue($entity, null);
					}
					continue;
				}

				$value = EntityHelper::getPropertyDbValue($entity, $relatedProperty->columnProperty);

				if ($relatedProperty->property->getType()->getName() === 'array') {
					// 1:M
					$relatedProperty->property->setValue($entity, $entityCache->getEntities($value, $relatedColumn));
				} else {
					// 1:1
					if ($otherEntity = $entityCache->getEntity($value, $relatedColumn)) {
						$relatedProperty->property->setValue($entity, $otherEntity);
					} elseif ($relatedProperty->property->getType()->allowsNull()) {
						$relatedProperty->property->setValue($entity, null);
					}
				}
			}
		}

	}

    /**
	 * Setuje defaultně [] / null (null jen pokud mozno)
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

        $containedModels = array_keys($fullContains->contains);
		//bdump(EntityHelper::getPropertiesOfOtherEntities(static::getEntityClass(), $containedModels), 'addOtherEntities of ' . static::class);

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

			$findQuery->getFullContains([$modelClass])->setNextUsedIndex($relatedColumn);

			if ($relatedProperty->property->getType()->getName() === 'array') {
				// Relace []
				foreach ($entities as $entity) {
					if ($relatedProperty->property->isInitialized($entity)) {
						continue;
					}
					$relatedProperty->property->setValue($entity, []);
					if (isset($entity->{$keyPropertyName})) {
						// Spojovací klíč má hodnotu
						$value = $entity->{$keyPropertyName};
						// Přidáme do conditions
						$modelContains->getConditions()->addOrCondition($relatedColumn, $value);

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
								$relatedModelContains->getConditions()->addOrCondition($relatedEntityRelatedProperty->relatedColumnProperty->column, $value);
							}
						}
					}
				}
			} else {
				// Relace ->
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
						$modelContains->getConditions()->addOrCondition($relatedColumn, $value);

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

								/*bdump("TADY", static::class);
								bdump($relatedProperty->relatedColumnProperty->entityClass);
								bdump($relatedEntityRelatedProperty);*/

								// Pokud je spojovací sloupec stejny, uz zname hodnotu a můžeme přidat conditions
								$relatedModelContains = $modelContains->contains[$relatedEntityRelatedProperty->relatedColumnProperty->entityClass::getModelClass()];
								$relatedModelContains->getConditions()->addOrCondition($relatedEntityRelatedProperty->relatedColumnProperty->column, $value);
							}
						}
					}
				}
			}
		}
/*
bdump($findQuery);
		exit;*/

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