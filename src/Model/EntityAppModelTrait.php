<?php

namespace Cesys\CakeEntities\Model;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Cesys\CakeEntities\Model\Entities\ColumnProperty;
use Cesys\CakeEntities\Model\Entities\EntityHelper;
use Cesys\CakeEntities\Model\Entities\RelatedProperty;
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
use Cesys\Utils\CachingIterator;

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


    protected array $contains = [];

	protected array $otherContains = [];

	protected array $defaultContainsParams = [];

	/**
	 * Sem se uloží v static::getFullContains aktuální useDbConfig jaký je před spuštěním findů
	 * @var string
	 * @internal
	 */
	private string $initUseDbConfig;

	//private ?array $multiUseDbConfigs = null;

	private static ?string $usedOtherContains = null;

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
					self::$usedOtherContains = null;
				}
				foreach (array_unique($query->path) as $modelClass) {
					/** @var static $Model */
					$Model = $this->getModel($modelClass);
					$Model->setInitUseDbConfig((string) $Model->useDbConfig);
					if ($resetTemporaryContains) {
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

	public function getParameterContains(array $fullContains): ?array
	{
		$isClientCall = __FUNCTION__ !== debug_backtrace(!DEBUG_BACKTRACE_PROVIDE_OBJECT|DEBUG_BACKTRACE_IGNORE_ARGS,2)[1]['function'];
		if ($isClientCall) {
			$fullContains = $fullContains[static::class]['contains'];
		}

		$contains = [];
		foreach ($fullContains as $modelClass => $modelParams) {
			if ($modelParams['contains'] === null) {
				$contains[] = $modelClass;
				continue;
			}

			if ($modelParams['contains']) {
				$modelParams['contains'] = $this->getParameterContains($modelParams['contains']);
				if ($modelParams['contains'] !== $this->getModel($modelClass)->getContains()) {
					$contains[$modelClass] = $modelParams;
				} else {
					$contains[] = $modelClass;
				}
				//$contains[$modelClass] = $modelParams;
			} else {
				unset($modelParams['contains']);
				//bdump($modelParams);
				//bdump($this->getModel($modelClass)->getContains());
				if ($modelParams === $this->getModel($modelClass)->getContains()) {
					$contains[] = $modelClass;
				} else {
					$contains[$modelClass] = $modelParams;
				}
			}

		}

		return $contains;
	}


	public function getContains(): array
	{
		return $this->contains;
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
	 * @return string[] -> vrací pole se všemi modely, jejichž entity jsou v definované vazbě tohoto modelu = maximální možné contains tohoto modelu
	 */
	public function getFullModelContains()
	{
		$relatedProperties = array_merge(
			EntityHelper::getPropertiesOfReferencedEntities(static::getEntityClass()),
			EntityHelper::getPropertiesOfRelatedEntities(static::getEntityClass())
		);
		$models = [];
		foreach ($relatedProperties as $relatedProperty) {
			$modelClass = $relatedProperty->relatedColumnProperty->entityClass::getModelClass();
			$models[$modelClass] = 1;
		}

		return array_keys($models);
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
	 * Platí jen pro první volání findEntities, včetně volání na připojení entit
	 * Použije se jedno z contains, definované v poli static::otherContains
	 *
	 * @param string $key
	 * @return void
	 */
	public function setOtherContains(?string $key): void
	{
		static::$usedOtherContains = $key;
	}


	public function getDefaultContainsParams(): array
	{
		return $this->defaultContainsParams;
	}

	/*public function setMultiUseDbConfigs(?array $useDbConfigs = null, bool $appendActive = false): void
	{
		if ( ! $useDbConfigs) {
			$this->multiUseDbConfigs = null;
			return;
		}
		if ($appendActive) {
			$useDbConfigs[] = (string) $this->useDbConfig;
		}
		$this->multiUseDbConfigs = array_values($useDbConfigs);
	}*/


	public function getInitUseDbConfig(): string
	{
		return $this->initUseDbConfig;
	}


	/**
	 * @param RelatedProperty $relatedProperty
	 * @param E $entity
	 * @return string
	 */
	public function getDynamicUseDbConfig(RelatedProperty $relatedProperty, CakeEntity $entity): string
	{
		$childModel = $this->getModel($relatedProperty->relatedColumnProperty->entityClass::getModelClass());
		if ($this->getInitUseDbConfig() === $childModel->getInitUseDbConfig()) {
			// Modely jsou v základu ve stejném configu
			// Tento (parent) model mohl být přepnutý do jiného. Pokud ano, zvazbený se přepne do toho samého
			return $entity->getUseDbConfig();
		}
		return (string) $childModel->useDbConfig;
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
            in_array($debugBacktrace[1]['function'], ['addOtherEntities', 'addSameEntities', 'buildSqlSubquery', 'appendSameEntities'])
            || in_array($debugBacktrace[2]['function'], ['addOtherEntities', 'addSameEntities', 'appendSameEntities'])
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
			Timer::start('$$FindStart$$ - ' . count(self::$findQueries));
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

		/*$useDbConfigs = [(string) $this->useDbConfig];
		if ($this->multiUseDbConfigs) {
			$useDbConfigs = $this->multiUseDbConfigs;
		}*/

		if ($findQuery->isOriginalCall() && $findQuery->isSystemCall()) {
			/*foreach ($useDbConfigs as $useDbConfig) {
				$this->useDbConfig = $useDbConfig;
				$findParams->setConditions(FindConditions::create($params['conditions']));
			}
			$this->useDbConfig = $this->getInitUseDbConfig();*/
			// FindConditions nejsou vytvořeny jedině v případě původního volání findEntities,
			// které je systémové, takže vložíme conditions do FindConditions
			$findParams->setConditions(FindConditions::create($params['conditions']));
			$findParams->setNextUsedIndex($this->primaryKey);
		}

		$useDbConfigs = $findParams->getUseDbConfigs();
		$entities = [];
		$interrupt = [];
		foreach ($useDbConfigs as $useDbConfig) {
			$interruptConfig = false;
			$this->useDbConfig = $useDbConfig;
			$entities[$useDbConfig] = $this->doFind($findParams, $findQuery->getEntityCache(), $findQuery->isSystemCall(), $findQuery->getOriginalParams(), $interruptConfig);
			if ($interruptConfig) {
				$interrupt[$useDbConfig] = $interruptConfig;
			}
		}
		$this->useDbConfig = $this->getInitUseDbConfig();
		$findParams->afterFind();
		if (count($interrupt) === count($useDbConfigs)) {
			// Všechny uvedené se mají interruptnout => end
			$findQuery->findEnd();
			return [];
		}

		$findParamsForFind = $findParams->contains;
		$entitiesToAppendOthers = $entities;
		$useDbConfigsIterator = new CachingIterator($useDbConfigs);
		foreach ($useDbConfigsIterator as $useDbConfig) {
			if (isset($interrupt[$useDbConfig])) {
				continue;
			}
			$this->useDbConfig = $useDbConfig;
			if ($findQuery->containsSameModel()) {
				// Řešíme rekurzi do vlastní tabulky
				$childFindParams = $findParams->contains[static::class];
				// Unsetneme pro find, tam kde je potřeba přidáme na začátek
				unset($findParamsForFind[static::class]);
				if ($findQuery->isRecursiveToSelfEndlessCacheCompatible()) {
					// Jde o nekonečnou rekurzi s podobnou cache
					if ( ! $findQuery->isSystemCall()) {
						// První volání, kde se před find nedoplňují RECURSIVE && nekonečné contains => chceme RECURSIVE
						$childFindParamsEntityCache = $findQuery->getEntityCache($childFindParams);
						$this->addSameEntitiesRecursive($childFindParams, $childFindParamsEntityCache, $entities[$useDbConfig]);
						$void = false;
						// Přiřadily se entity do vl. tabulky, entity v relaci z ostatních modelů se připojí až níže
						if ($findParams === $childFindParams) {
							// Pokud jsou FindParams totožné (což je pouze podmnožina možností FindQuery::isRecursiveToSelfEndlessCacheCompatible),
							// chceme entity v relaci z ostatních modelů připojit rovnou všem získaným entitám, ne jen těm z prvního findu
							$entitiesToAppendOthers[$useDbConfig] = $this->doFind($childFindParams, $childFindParamsEntityCache, true, null, $void);
							if ($useDbConfigsIterator->isLast()) {
								// Při poslední iteraci vyresetujeme
								// Jsme v tomto případě určite inFindParamsRecursion, takže use se nemá odečíst
								$childFindParams->afterFind(false);
							}
						} else {
							// Zde nás vrácené entity nezajímají (ty co potřebujeme už máme), Do tohodle nodu ještě půjdeme znova, jen jsme si fetchnuli "dopředu"
							$this->doFind($childFindParams, $childFindParamsEntityCache, true, null, $void);
							// Vytáhli jsme si entity "dopředu", vyčistíme conditions
							$childFindParams->getConditions()->clear();
							// A přidáme je flat, tím se nám správně v podřazeném volání připojí ostatní entity v relaci
							$this->addSameEntities($entities[$useDbConfig]);
							$findParamsForFind = [static::class => $childFindParams] + $findParamsForFind;
						}
					} else {
						// Recursive při systémovém volání se přidává do FindConditions ve static::doFind(), tj. bylo vyřešeno výše
						// A byly vráceny správně entity podle toho, jestli jsou FindParams totožné či nikoli
						if ($findParams === $childFindParams) {
							// Nic nemusíme dělat
						} else {
							// Zde musíme vysílat do dalších findů, kvůli tomu, aby se správně přiřadily ostatní entity v relaci
							// Chceme připojit jenom k entitám, které jsou na prvním "levelu"
							// A přidáme je flat, tím se nám správně v podřazeném volání připojí ostatní entity v relaci
							// static::doFind() je napsaný tak, že v tomto případě nám vrátilo jen entity na "prvním levelu" tj. to, co bylo v OR conditions
							// tj. other připojimeme správně
							$this->addSameEntities($entities[$useDbConfig]);
							$findParamsForFind = [static::class => $childFindParams] + $findParamsForFind;
						}
					}
					// V obou případech už je find vyřešen
				} else {
					// Jsou konečné FindParams, nebo nekompatibilní pro RECURSIVE, pouze flat
					$this->addSameEntities($entities[$useDbConfig]);
					// V tomto případě find zase přidáme - na začátek
					$findParamsForFind = [static::class => $childFindParams] + $findParamsForFind;
				}
			}
			// Připojení entit z ostatních tabulek
			$this->addOtherEntities($entitiesToAppendOthers[$useDbConfig]);
		}
		$this->useDbConfig = $this->getInitUseDbConfig();

		// Volání findEntities u modelů
		foreach ($findParamsForFind as $modelClass => $modelFindParams) {
			$isInFindParamsRecursion = $findQuery->isChildModelInFindParamsRecursion($modelClass);
			if ($modelFindParams->hasNextStandardFind($isInFindParamsRecursion)) {
				// Přeskočení, tato 'větev' FindParams se objevuje ještě minimálně 1x ve stromu původních FindParams, tj. ještě budeme
				// určitě volat v rámci tohoto celkového findu. Můžeme přeskočit, FindConditions pro tuto část větve již byly připojeny
				if ( ! $isInFindParamsRecursion) {
					// Jedná se o nevyslání do findu, které ale bylo v nerekurzivních FindParams, a tudíž připočteno do použití
					// Volá se skipUse, protože chceme jen snížit 'willBeUsed', ale ne smazat nastavené indexy
					$modelFindParams->skipUse();
				}
				continue;
			}
			/** @var static $Model */
			$Model = $this->getModel($modelClass);
			$Model->findEntities([], []);
		}


        if ($findQuery->findEnd()) {
			//bdump($this->getModelCache());
			array_pop(self::$findQueries);
			Timer::stop();
			Timer::getResults();
			//bdump($findQuery, static::class);
			// Debug
			$checkedEntities = [];
			$paramsQueue = new \SplQueue();
			$paramsQueue[] = [$findQuery->findParams, $entities[(string) $this->useDbConfig]];
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
            return $entities[(string) $this->useDbConfig];
        }

        return [];
    }


	/**
	 * @param string $initUseDbConfig
	 * @return void
	 * @intarnal pouze pro vnitřní použití
	 */
	public function setInitUseDbConfig(string $initUseDbConfig): void
	{
		$this->initUseDbConfig = $initUseDbConfig;
	}


	/**
	 * @param FindQuery $findQuery
	 * @param bool $interrupt V nadřazeném volání už se dále nemají připojovat entity v relaci
	 * @return CakeEntity[] Entity, které odpovídají FindConditions ve FindParams => K nim je třeba dále připojit jejich entity v relaci
	 */
	private function doFind(
		FindParams $findParams,
		EntityCache $entityCache,
		bool $isSystemCall,
		?CakeParams $originalParams,
		bool &$interrupt
	): array
	{
		$interrupt = false;

		// Základní potlačení nekonečné rekurze => stampOrConditions vytvoří "otisk" conditions,
		// a pokud se dostaneme na ty samé "znovu", ukončíme => interrupt
		if ( ! $findParams->getConditions()->stampOrConditions() && ! $findParams->getConditions()->hasStringConditions()) {
			// Stejné FindOrConditions už byly a není volání recursive
			$interrupt = true;
			return [];
		}

		// Pro systémová volání zde projdeme OR conditions - sloupce a jejich hodnoty
		// Pokud pro danou hodnotu sloupce ještě není index, je vytvořen = inicializace této hodnoty v cache jako [] => To je nutné, žádné entity ve výsledku nemusí existovat...
		// Pokud už naopak existuje, je daná hodnota odebrána z or conditions = víme, že tato hodnota je už v cache
		$preparedIndexes = [];
		$cachedEntities = [];
		if ($isSystemCall/* && ! $findParams->getConditions()->getOr()->isEmpty()*/) {
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

		$onlyIndexes = null;

		// Pro systémová volání s nekonečnou rekurzí do vlastní tabulky
		if (
			$isSystemCall  // Pokud není sytémové volání = jsou uživatelovy params, nemůžeme zasahovat
			&& ! $findParams->getConditions()->isEmpty() // Bez podmínek nemá smysl -> výsledek je 0 entit
			&& 	$findParams->isRecursiveToSelfEndlessCacheCompatible() // Je rekurze do vl. tabulky a jde o nekonečnou rekurzi se stejnými params a kompatibilními contains todo speed uložit
			&& ! $findParams->getConditions()->hasStringConditions()
			// stringConditions vytváří framework v jediném případě - že se ptá na rekurzi do vlastní tabulky
			// Tedy pokud jsou zde už definovány, je toto samotné volání findEntites kvůli získání záznamů ze stejné tabulky, do něj zde zasahovat nechceme
			// K čemuž dojde jen u prvního nesystémového volání s nekonečnou rekurzí do vlastní tabulky
		) {
			if ($findParams !== $findParams->contains[static::class]) {
				// Pokud nejsou ekvivalentní FindParams, budeme vracet jenom ty entity, které jsou v původních OR conditions
				$onlyIndexes = $findParams->getConditions()->getLastOrConditionsHistory()->toArray();
			}
			// Recursive volání do vl. tabulky, podmínky z FindConditions se transformují do subquery v recursive
			$this->addSameEntitiesRecursive($findParams, $entityCache, [],true);
		}


		// "Hlavní" část -> jediné volání \AppModel::find
		$entities = [];
		//bdump($findQuery, 'BEFOREFIND - ' . static::class);
		if ( ! $isSystemCall || ! $findParams->getConditions()->isEmpty()) {
			// Uživatelovo volání, nebo když je neco v conditions
			if ( ! $isSystemCall) {
				// FindConditions jsou při nesystémovém volání vždy zde prázdné => jen uživatelovo params
				$params = $originalParams->toArray();
			} else {
				$params = $findParams->containsParams->toArray();
				$params['conditions'][] = $findParams->getConditions()->toArray();
			}
			// Fields jsou vždy všechny co jsou na entitě, jinak psycho
			$params['fields'] = $this->getFields();
			// Vždy -1
			$params['recursive'] = -1;
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
		$entities = $entities + $cachedEntities;

		if (isset($onlyIndexes)) {
			// Bylo zde připojené recursive a chceme vrátit (rozdílné FindParams) jenom přímo dotazované entity přes OR
			$onlyEntities = [];
			foreach ($onlyIndexes as $column => $values) {
				foreach ($values as $value) {
					$onlyEntities += $entityCache->getEntities($value, $column);
				}
			}
			$entities = $onlyEntities;
		}

		return $entities;
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
	 * Při přetížení vždy volat tenot parent!!!
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
		$entity = $entityClass::createFromDbArray($data);
		$entity->setUseDbConfig($this->useDbConfig);
        return $entity;
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
            $this->id = false; // Místo volání create(), které je naprd
        }
        $result = $this->save([$this->alias => array_merge($data, $appendData)], $validate, $fieldList);
        
        if ($result) {
			foreach ($result[$this->alias] as $column => $value) {
				if ($columnProperty = EntityHelper::getColumnPropertiesByColumn(static::getEntityClass())[$column] ?? null) {
					EntityHelper::appendFromDbValue($entity, $columnProperty, $value);
				}
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
        $paginateParams['fields'] = ["$this->alias.$this->primaryKey"];

        if (isset($paginateParams['contains'])) {
            $joins = $this->getJoins($paginateParams['contains'], $this);
        } else {
			$paginateParams['conditions'] = array_merge($paginateParams['conditions'] ?? [], $conditions); // todo?
            return $paginateParams;
        }
        unset($paginateParams['contains']);

        $models = $allModels = array_keys($joins);
		$includedModels = array_flip($this->findModelsInConditions($models, $conditions));
        $joins = array_intersect_key($joins, $includedModels);
        $finalJoins = [];
        foreach ($joins as $join) {
            $finalJoins = array_merge($join, $finalJoins);
        }
        $paginateParams['joins'] = array_values($finalJoins);
        $paginateParams['group'] = ["$this->alias.$this->primaryKey"];

		$paginateParamsConditions = [];
		foreach ($paginateParams['conditions'] ?? [] as $key => $paginateParamsCondition) {
			$models = $allModels;
			$foundModels = array_flip($this->findModelsInConditions($models, [$key => $paginateParamsCondition]));
			if ( ! array_diff_key($foundModels, $includedModels)) {
				// Jedině v případě, že všechny modely v $paginateParamCondition
				$paginateParamsConditions[$key] = $paginateParamsCondition;
			}
		}
		$paginateParams['conditions'] = array_merge($paginateParamsConditions, $conditions); // todo merge v pohodě?

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
		if (isset($temporaryContains)) {
			$defaultContains = $temporaryContains;
		} elseif (isset(static::$usedOtherContains) && isset($this->otherContains[static::$usedOtherContains])) {
			$defaultContains = $this->otherContains[static::$usedOtherContains];
		} else {
			$defaultContains = $this->contains;
		}

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
    private function addSameEntitiesRecursive(
		FindParams $findParams,
		EntityCache $entityCache,
		array $entities,
		bool $fromSubQuery = false
	): void
    {
		if ($fromSubQuery) {
			$entities = [];
		} elseif ( ! $entities) {
            return;
        }

		/*$findParams = $fromSubQuery ? $findQuery->getFindParams() : $findQuery->getFindParams([static::class]);
		$entityCache = $findQuery->getEntityCache($findParams);*/

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
			/** @var \AppModel&EntityAppModelTrait $Model */
			$Model = $this->getModel($modelClass);

			$keyPropertyName = $relatedProperty->columnProperty->propertyName;
			// Vazební sloupec v cizí tabulce
			$relatedColumn = $relatedProperty->relatedColumnProperty->column;
			// Najdeme modelovo FindParams
			$modelFindParams = $findQuery->getFindParams([$modelClass]);

			if ($modelClass === 'ECurrency') {
				bdump($findParams, 'chce E curr');
			}

			if ($relatedProperty->property->getType()->getName() === 'array') {
				// Relace []
				foreach ($entities as $entity) {
					$isInitialized =  $relatedProperty->property->isInitialized($entity);
					if ( ! $isInitialized) {
						$relatedProperty->property->setValue($entity, []);
					}
					if (isset($entity->{$keyPropertyName})) {
						// Spojovací klíč má hodnotu
						$value = EntityHelper::getPropertyDbValue($entity, $relatedProperty->columnProperty);
						$Model->useDbConfig = $this->getDynamicUseDbConfig($relatedProperty, $entity);
						$modelFindParams->setNextUsedIndex($relatedColumn);
						// Přidáme do conditions
						$modelFindParams->getConditions()->addOrCondition($relatedColumn, $value);

						if ( ! $isInitialized) {
							$findQuery->getEntityCache($modelFindParams)->setOnAdd($relatedProperty, $value, function (CakeEntity $otherEntity) use ($relatedProperty, $entity) {
								$entity->{$relatedProperty->property->getName()}[$otherEntity->getPrimary()] = $otherEntity;
							});
						}
						$Model->useDbConfig = $Model->getInitUseDbConfig();
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
						// Spojovací klíč má hodnotu
						$value = EntityHelper::getPropertyDbValue($entity, $relatedProperty->columnProperty);
						// Přepínání
						$Model->useDbConfig = $this->getDynamicUseDbConfig($relatedProperty, $entity);
						$modelFindParams->setNextUsedIndex($relatedColumn);
						// Přidáme do conditions
						$modelFindParams->getConditions()->addOrCondition($relatedColumn, $value);

						if ( ! $isInitialized) {
							$findQuery->getEntityCache($modelFindParams)->setOnAdd($relatedProperty, $value, function (CakeEntity $otherEntity) use ($relatedProperty, $entity) {
								$relatedProperty->property->setValue($entity, $otherEntity);
							});
						}
						$Model->useDbConfig = $Model->getInitUseDbConfig();
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