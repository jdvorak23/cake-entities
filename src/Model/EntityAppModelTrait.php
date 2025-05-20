<?php

namespace Cesys\CakeEntities\Model;

use Cesys\CakeEntities\Entities\CakeEntity;
use Cesys\CakeEntities\Entities\Relation;
use Cesys\CakeEntities\Model\LazyModel\ModelLazyModelTrait;
use Cesys\Utils\Arrays;
use Cesys\Utils\Reflection;
use Cesys\Utils\Strings;

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

    /**
     * Třída entity odpovídající modelu
     * @var string
     */
    protected string $entityClass;

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

    /**
     * Pro interní cachování při volání findEntities
     * @var array
     */
    private array $findCache = [];

    /**
     * Pro interní cachování při volání findEntities
     * @var array
     */
    private array $foundByColumn = [];

    /**
     * Pro interní cachování při volání findEntities
     * @var array
     */
    private ?array $usedContains;

    /**
     * Pro interní cachování při volání findEntities
     * @var array
     */
    private static array $findPath = [];

    /**
     * Pro interní cachování při volání findEntities
     * @var array
     */
    private array $modelConditions = [];


    /**
     * Získání třídy Entity, pokud není vše ve standardním namespace, je nutno v modelu přetížit,
     * nebo nastavit (např. v konstukoru), nebo rovnou přepsat protected
     * Přepsat si tam, kde to je jinak
     * @return class-string<E>
     */
    public function getEntityClass(): string
    {
        if (isset($this->entityClass)) {
            return $this->entityClass;
        }
        $classWithoutNamespace = static::class;
        $database = \ConnectionManager::getInstance()->config->{$this->useDbConfig}['database'];
        $database = static::$SERVER_DEFAULT_SUB_NAMESPACE[$database] ?? $database;
        $subNamespace = ucfirst(Strings::fromSnakeCaseToCamelCase($database));
        return $this->entityClass = "\\Cesys\\CakeEntities\\Entities\\$subNamespace\\$classWithoutNamespace";
    }


    /**
     * @param class-string<E> $entityClass
     * @return void
     */
    public function setEntityClass(string $entityClass): void
    {
        if ( ! is_a($entityClass, CakeEntity::class, true)) {
            throw new \InvalidArgumentException('$entityClass must be an instance of CakeEntity.');
        }
        $this->entityClass = $entityClass;
    }


    /**
     * @param bool $normalized
     * @return array
     */
    public function getContains(bool $normalized = true): array
    {
        return $normalized ? $this->getNormalizedContains($this->contains) : $this->contains;
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
     * @param ?array $contains null = reset (když je null, nic to nedělá)
     * @return void
     */
    public function setTemporaryContains(?array $contains): void
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
     * @param array $params
     * @param array|null $contains
     * @param bool $useCache
     * @return E[]
     */
    public function findEntities(array $params = [], ?array $contains = null, bool $useCache = false): array
    {
        $isFirstCall = false;
        $isOriginalCall = false;
        if ( ! self::$findPath) {
            // První (klientovo) volání
            $isOriginalCall = true;
        }
        if ( ! in_array(static::class, self::$findPath, true)) {
            // První volání tohoto modelu, vyčištění cache
            $this->findCache = $useCache ? $this->entities : [];
            $this->foundByColumn = [];
            $this->modelConditions = [];
            // Uložení contains prvního volání tohoto modelu
            $this->usedContains = $contains;
            $isFirstCall = true;
        }
        self::$findPath[] = static::class;
        if ($contains === null) {
            // Pokud není definované contains pro toto konkrétní volání, použijeme to, co bylo při prvním volání
            // Jinak by při kruhové vazbě při druhém a dalším volání byl vždy default, což nedává moc smysl
            $contains = $this->usedContains;
        }

        $params['recursive'] = -1;
		if ( ! isset($params['fields']) || ! $params['fields'] || ! is_array($params['fields'])) {
			// Pokud nejsou fieldy v params, bereme ty, co má entita definované
			$params['fields'] = [];
			/** @var class-string<E> $entityClass */
			$entityClass = $this->getEntityClass();
			foreach(array_keys($entityClass::getProperties()) as $propertyName) {
				$columnName = Strings::fromCamelCaseToSnakeCase($propertyName);
				if ($this->schema($columnName)) {
					$params['fields'][] = $columnName;
				}
			}
		}
        // Primární klíč musí být přítomen
        if (is_array($params['fields']) && ! in_array($this->primaryKey, $params['fields'], true)) {
            $params['fields'][] = $this->primaryKey;
        }

        $entities = false;
        if ( ! $isOriginalCall || $useCache) {
            if (isset($params['conditions']['OR'][$this->primaryKey])) {
                $ids = $params['conditions']['OR'][$this->primaryKey];
                $idsToFind = array_diff($ids, array_keys($this->findCache));
                if ($idsToFind) {
                    $params['conditions']['OR'][$this->primaryKey] = $idsToFind;
                } else {
                    unset($params['conditions']['OR'][$this->primaryKey]);
                }
            }
            foreach ($this->foundByColumn as $column => $foundIds) {
                if (isset($params['conditions']['OR'][$column])) {
                    $ids = $params['conditions']['OR'][$column];
                    $idsToFind = array_diff($ids, $foundIds);
                    if ($idsToFind) {
                        $params['conditions']['OR'][$column] = $idsToFind;
                    } else {
                        unset($params['conditions']['OR'][$column]);
                    }
                }
            }
            if (empty($params['conditions']['OR'])) {
                unset($params['conditions']['OR']);
            }
            if (empty($params['conditions'])) {
                //return $this->findCache;
                if ($useCache) {
                    // Pokud jedeme z cache, nemůžeme jen tak vyskočit, musí se připojit entity, které nemusely být před tím připojené....
                    $entities = $this->findCache;
                } else {
                    // Jinak rovnou vracíme, u nových entit nehrozí
                    return $this->findCache;
                }
            }
        }

        if ($entities === false) {
            $entitiesData = $this->find('all', $params);
            $entities = [];
            foreach ($entitiesData as $entityData) {
                if (isset($this->findCache[$entityData[$this->alias][$this->primaryKey]])) {
                    // Pokud je fetchnuta znova entita, která už je v cache, nepřepisuje se!
                    continue;
                }
                $entity = $this->createEntity($entityData);
                $entities[$entity->getPrimary()] = $entity;
                $this->entities[$entity->getPrimary()] = $entity;
                $this->findCache[$entity->getPrimary()] = $entity;
            }

            if ( ! $isOriginalCall && ! isset($params['conditions']['AND'])) {
                // Jsme v rekurzi a nejdeme sem z addSameEntities(), kde je jediné místo, kde traita přidává 'AND'
                foreach ($params['conditions']['OR'] ?? [] as $column => $ids) {
                    if ($column === $this->primaryKey) {
                        continue;
                    }
                    $this->foundByColumn[$column] = array_merge($this->foundByColumn[$column] ?? [], $ids);
                }
                $entities = $this->findCache;
            }
        }
        // Nalezení entit ve vlastní tabulce (přes nějaké parent_id)
        $allEntities = $this->addSameEntities($entities, $contains, $params['fields'], $useCache);
        // Připojení entit z ostatních tabulek
        $this->addOtherEntities($allEntities, $contains, $useCache);

        if ($isFirstCall) {
            $this->temporaryContains = null;
        }
        if ($isOriginalCall) {
            bdump(self::$findPath);
            self::$findPath = [];
			return $entities;
        }

        return $allEntities;
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
        $entityClass = $this->getEntityClass();
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

            if (isset($result[$this->alias]['created']) && $property = $entity::getProperties()['created'] ?? null) {
                if ($property->getType() instanceof \ReflectionNamedType && is_a($property->getType()->getName(), \DateTime::class, true) ) {
                    $entity->created = $property->getType()->getName()::createFromFormat('Y-m-d H:i:s', $result[$this->alias]['created']);
                }
            }
            if (isset($result[$this->alias]['modified'])  && $property = $entity::getProperties()['modified'] ?? null) {
                if ($property->getType() instanceof \ReflectionNamedType && is_a($property->getType()->getName(), \DateTime::class, true) ) {
                    $entity->modified = $property->getType()->getName()::createFromFormat('Y-m-d H:i:s', $result[$this->alias]['modified']);
                }
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


    private function getDefaults(): array
    {
        if (isset($this->defaults)) {
            return $this->defaults;
        }
        $this->defaults = [];
        foreach ($this->schema() as $column => $schema) {
            if ($schema['default'] === null && ! $schema['null']) {
                continue;
            }
            if (in_array($column, ['created', 'modified', 'created_by', 'modified_by'], true)) {
                continue;
            }
            $this->defaults[$column] = $schema['default'];
        }
        return $this->defaults;
    }


    private function getNormalizedContains(?array $contains = null): array
    {
        $normalizedContains = [];
        $defaultContains = $this->temporaryContains ?? $this->contains;
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
     * @param E[] $entities
     * @param array|null $contains
     * @param array $fields
     * @param bool $useCache
     * @return E[]
     */
    private function addSameEntities(array $entities, ?array $contains, array $fields, bool $useCache): array
    {
        if ( ! $entities) {
            return [];
        }

        if ( ! $contains = $this->getNormalizedContains($contains)) {
            return $entities;
        }
        $containedModels = array_keys($contains);

        /** @var class-string<E> $entityClass */
        $entityClass = $this->getEntityClass();
        $selects = [
            'ascendants' => [],
            'descendants' => [],
        ];
        $callbacks = [];
        $idsToFetch = [];
        /** @var \ReflectionProperty $property */
        foreach ($entityClass::getPropertiesOfReferencedEntities() as $keyPropertyName => $property) {
            /** @var class-string<E> $referencedEntityClass */
            $referencedEntityClass = $property->getType()->getName();
            $modelClass = $referencedEntityClass::getModelClass();
            if ( ! in_array($modelClass, $containedModels, true) || $modelClass !== static::class) {
                // Musí být v contains a zde řešíme jenom rekurzi do vlastní tabulky
                continue;
            }

            foreach ($entities as $entity) {
                if ($property->isInitialized($entity)) {
                    continue;
                }
                $idsToFetch[] = $entity->getPrimary();
            }
            $column = Strings::fromCamelCaseToSnakeCase($keyPropertyName);
            $selects['ascendants'][] = $column;
            $callbacks[] = function () use ($property, $keyPropertyName) {
                foreach ($this->findCache as $entity) {
                    if ($property->isInitialized($entity)) {
                        continue;
                    }
                    if (isset($entity->{$keyPropertyName}) && isset($this->findCache[$entity->{$keyPropertyName}])) {
                        $property->setValue($entity, $this->findCache[$entity->{$keyPropertyName}]);
                    } elseif ($property->getType()->allowsNull()) {
                        $property->setValue($entity, null);
                    }
                }
            };
        }

        /** @var Relation $relation */
        foreach ($entityClass::getPropertiesOfRelatedEntities() as $propertyName => $relation) {
            if( ! $this->schema($relation->column)) {
                // Musí být sloupec ve schema
                continue;
            }
            $modelClass = $relation->relatedEntityClass::getModelClass();
            if ( ! in_array($modelClass, $containedModels, true) || $modelClass !== static::class) {
                // Musí být v contains a zde řešíme jenom rekurzi do vlastní tabulky
                continue;
            }
            foreach ($entities as $entity) {
                if ($relation->property->isInitialized($entity)) {
                    continue;
                }
                $idsToFetch[] = $entity->getPrimary();
            }
            $selects['descendants'][] = $relation->column;
            $callbacks[] = function () use ($relation, $propertyName) {
                $relatedPropertyName = Strings::fromSnakeCaseToCamelCase($relation->column);
                foreach ($this->findCache as $entity) {
                    if ($relation->property->isInitialized($entity)) {
                        // Už mohlo být přiřazeno v rekurzi, nemá smysl znova vyhledávat
                        continue;
                    }
                    $entity->{$propertyName} = array_filter($this->findCache, fn ($relatedEntity) => isset($relatedEntity->{$relatedPropertyName}) && $relatedEntity->{$relatedPropertyName} === $entity->getPrimary());
                }
            };
        }

        $idsToFetch = $this->filterIds($idsToFetch);
        if ($idsToFetch) {
            $bindingColumns = array_unique(array_merge($selects['ascendants'], $selects['descendants'], [$this->primaryKey]));
            $tBindingColumns = array_map(fn($column) => "t.$column", $bindingColumns);
            $bindingColumns = implode(', ', $bindingColumns);
            $tBindingColumns = implode(', ', $tBindingColumns);
            $ascendantsOns = [];
            $descendantsOns = [];
            foreach ($selects['ascendants'] as $column) {
                $ascendantsOns[] = "t.$this->primaryKey = ascendants.$column";
            }
            foreach ($selects['descendants'] as $column) {
                $descendantsOns[] = "t.$column = descendants.$this->primaryKey";
            }
            $values = implode(', ', $idsToFetch);

            $sql = 'WITH RECURSIVE';
            $sqlEnd = '';
            if ($ascendantsOns) {
                $ascendantsOnsText = implode(' OR ', $ascendantsOns);
                $sql .= "
                    ascendants AS (
                        SELECT $bindingColumns
                        FROM $this->useTable
                        WHERE $this->primaryKey IN ($values)
                        UNION ALL
                        SELECT $tBindingColumns
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
                        SELECT $bindingColumns
                        FROM $this->useTable
                        WHERE $this->primaryKey IN ($values)
                        UNION ALL
                        SELECT $tBindingColumns
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

            $params = [
                'conditions' => [
                    "$this->primaryKey IN ($sql)",
                ],
                'fields' => $fields
            ];
            if ($this->findCache) {
                $params['conditions']['AND']['NOT'] = [$this->primaryKey => array_keys($this->findCache)];
            }

            $entities += $this->findEntities($params, [], $useCache);
        }
        if ( ! empty($callbacks)) {
            Arrays::invoke($callbacks);
        }

        return $entities;
    }

    /**
     * @param E[] $entities
     * @param ?array $contains
     * @param bool $useCache
     */
    private function addOtherEntities(array $entities, ?array $contains, bool $useCache): void
    {
        if ( ! $entities) {
            return;
        }

        if ( ! $contains = $this->getNormalizedContains($contains)) {
            return;
        }
        $containedModels = array_keys($contains);


        /** @var class-string<E> $entityClass */
        $entityClass = $this->getEntityClass();
        $modelsWithNewConditions = [];
        $callbacks = [];
        /** @var \ReflectionProperty $property */
        foreach ($entityClass::getPropertiesOfReferencedEntities() as $keyPropertyName => $property) {
            /** @var class-string<E> $referencedEntityClass */
            $referencedEntityClass = $property->getType()->getName();
            $modelClass = $referencedEntityClass::getModelClass();
            if ( ! in_array($modelClass, $containedModels, true) || $modelClass === static::class) {
                // Musí být v contains a řešíme vše MIMO rekurzi do vlastní  tabulky
                continue;
            }
            $Model = $this->getModel($modelClass);
            if ( ! in_array(EntityAppModelTrait::class, Reflection::getUsedTraits(get_class($Model)))) {
                throw new \InvalidArgumentException("Model '$modelClass' included in \$contains parameter is not instance of EntityAppModel.");
            }

            foreach ($entities as $entity) {
                if ($property->isInitialized($entity)) {
                    continue;
                }
                if (isset($entity->{$keyPropertyName})) {
                    if (isset($this->modelConditions[$modelClass][$Model->primaryKey]) && in_array($entity->{$keyPropertyName}, $this->modelConditions[$modelClass][$Model->primaryKey], true)) {
                        //continue;
                    } else {
						$modelsWithNewConditions[] = $modelClass;
						$this->modelConditions[$modelClass][$Model->primaryKey][] = $entity->{$keyPropertyName};
					}

                    $callbacks[$modelClass][] = function ($entities) use ($property, $entity, $keyPropertyName) {
						/*if (static::class === 'EfFInvoice' && $property->getName() === 'fCurrency') {
							bdump($entities);
						}*/
						if ($property->isInitialized($entity)) {
							return;
						}
						if (isset($entities[$entity->{$keyPropertyName}])) {
							$property->setValue($entity, $entities[$entity->{$keyPropertyName}]);
						}/* elseif ($property->getType()->allowsNull()) {
							$property->setValue($entity, null);
						}*/
                    };
                } elseif ($property->getType()->allowsNull()) {
                    $property->setValue($entity, null);
                }
            }

        }

        /** @var Relation $relation */
        foreach ($entityClass::getPropertiesOfRelatedEntities() as $propertyName => $relation) {
            $modelClass = $relation->relatedEntityClass::getModelClass();
            if ( ! in_array($modelClass, $containedModels, true) || $modelClass === static::class) {
                // Musí být v contains a řešíme vše MIMO rekurzi do vlastní  tabulky
                continue;
            }

            $Model = $this->getModel($modelClass);
            if ( ! in_array(EntityAppModelTrait::class, Reflection::getUsedTraits(get_class($Model)))) {
                throw new \InvalidArgumentException("Model '$modelClass' included in \$contains parameter is not instance of EntityAppModel.");
            }

            if ( ! $Model->schema($relation->column)) {
                // Musí být sloupec ve schema
                continue;
            }

            foreach ($entities as $entity) {
                if ($relation->property->isInitialized($entity)) {
                    continue;
                }
                if (isset($this->modelConditions[$modelClass][$relation->column]) && in_array($entity->getPrimary(), $this->modelConditions[$modelClass][$relation->column], true)) {
                    continue;
                }
                $modelsWithNewConditions[] = $modelClass;
                $this->modelConditions[$modelClass][$relation->column][] = $entity->getPrimary();
                $callbacks[$modelClass][] = function ($entities) use ($relation, $entity, $propertyName) {
                    $relatedPropertyName = Strings::fromSnakeCaseToCamelCase($relation->column);
                    if ($relation->property->isInitialized($entity)) {
                        // Už mohlo být přiřazeno v rekurzi, nemá smysl znova vyhledávat
                        return;
                    }
                    $entity->{$propertyName} = array_filter($entities, fn ($relatedEntity) => isset($relatedEntity->{$relatedPropertyName}) && $relatedEntity->{$relatedPropertyName} === $entity->getPrimary());
                };
            }
        }
		/*if (static::class === 'EfFInvoice') {
			bdump($entities);
			bdump($this->modelConditions);
			bdump($callbacks['EfFCurrency']);
			bdump(array_unique($modelsWithNewConditions));
		}
		bdump($this->modelConditions);*/
		//foreach (array_unique($modelsWithNewConditions) as $modelClass) {
        foreach (array_keys($this->modelConditions) as $modelClass) {
            $Model = $this->getModel($modelClass);
            $modelContains = null;
            if ($params = is_array($contains[$modelClass]) ? $contains[$modelClass] : []) {
                $modelContains = $params['contains'] ?? null;
                unset($params['contains']);
            }
            // Zde zásadní krok na potlačení rekurze při zapnuté cache
            foreach ($this->modelConditions[$modelClass] as $column => $values) {
                $values = $this->filterIds($values);
                $params['conditions']['OR'][$column] = $values;
                if (
                    $column !== $Model->primaryKey
                    && isset($params['fields'])
                    && ! in_array($column, $params['fields'], true)
                ) {
                    $params['fields'][] = $column;
                }
            }

            $otherEntities = isset($params['conditions'])
                ? $Model->findEntities($params, $modelContains, $useCache)
                : [];

            if ( ! empty($callbacks[$modelClass])) {
                Arrays::invoke($callbacks[$modelClass], $otherEntities);
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