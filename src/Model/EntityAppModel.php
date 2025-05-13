<?php

namespace Cesys\CakeEntities\Model;

use Cesys\CakeEntities\Entities\CakeEntity;
use Cesys\CakeEntities\Entities\Relation;
use Cesys\Utils\Arrays;
use Cesys\Utils\Reflection;
use Cesys\Utils\Strings;

/**
 * @template E of CakeEntity
 */
trait EntityAppModel
{
	static array $SERVER_DEFAULT_SUB_NAMESPACE = [
		's_server2' => 'server',
		't_server2' => 'server',
		'u_server2' => 'server',
	];

    public array $contains = [];

    /**
     * Cache fetchnutých / uložených entit
     * @var array
     */
    protected array $entities = [];

    /**
     * Pro zapamatování, kterého id se uložené Model::__exists vztahuje
     * @var mixed
     */
    protected $existsId;

    /**
     * Zde je uloženo to, co vrátilo poslední volání save()
     * @var array|bool
     */
    protected $lastSaveResult;

    /**
     * Uložené default hodnoty všech sloupců tabulky, které mají default
     * @var array
     */
    private array $defaults;

    /**
     * Třída entity odpovídající modelu
     * @var string
     */
    private string $entityClass;


    /**
     * Přepsat si tam, kde to je jinak
     * @return class-string<E>
     */
    public function getEntityClass(): string
    {
        if (isset($this->entityClass)) {
            return $this->entityClass;
        }
        $classWithoutNamespace = static::class;
        // todo mozna zase static, nebo zavislost na usedbconfig
        $database = \ConnectionManager::getInstance()->config->{$this->useDbConfig}['database'];
        $database = static::$SERVER_DEFAULT_SUB_NAMESPACE[$database] ?? $database;
        $subNamespace = ucfirst(Strings::fromSnakeCaseToCamelCase($database));
        return $this->entityClass = "\\Cesys\\CakeEntities\\Entities\\$subNamespace\\$classWithoutNamespace";
    }


    /**
     * @param array $params
     * @param array|null $contains
     * @return ?E
     */
    public function findEntity(array $params = [], ?array $contains = null)
    {
        $params['limit'] = 1;
        $entities = $this->findEntities($params, $contains);
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
     * @return E[]
     */
    public function findEntities(array $params = [], ?array $contains = null): array
    {
        $params['recursive'] = -1;
		if ( ! isset($params['fields']) || ! $params['fields']) {
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
        if (is_array($params['fields']) && ! in_array($this->primaryKey, $params['fields'], true)) {
            $params['fields'][] = $this->primaryKey;
        }
        $entitiesData = $this->find('all', $params);

        $entities = [];
        foreach ($entitiesData as $entityData) {
            $entity = $this->createEntity($entityData);
            $entities[$entity->getPrimary()] = $entity;
            $this->entities[$entity->getPrimary()] = $entity;
        }

        $allEntities = $entities;
        $this->addReferencedEntities($allEntities, $contains, $params);
        // todo children
        $this->addRelatedEntities($allEntities, $contains);

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
        $fetchIds = [];
        if ($useCache) {
            foreach ($ids as $id) {
                if ( ! isset($this->entities[$id])) {
                    $fetchIds[] = $id;
                }
            }
        } else {
            $fetchIds = $ids;
        }

        if ($fetchIds) {
            $this->findEntities(['conditions' => [$this->primaryKey => $fetchIds]], $contains);
        }

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


    public function save($data = null, $validate = true, $fieldList = array())
    {
        $this->set($data); // Stejně to je přiřazeno v parent::save(), tím se doplní do $this->data i s alias, pokud nebyl
        // Navíc zde již finálně víme id -> pokud bylo v $data, přepsalo / nastavilo hodnotu v $this->id, nebo se bere dříve nastavená, nebo není

        // Vyřeší omylem ponechané klíče v $data, tyto nemá smysl posílat do save, protože se mají generovat automaticky
        unset($this->data[$this->alias]['created']);
        unset($this->data[$this->alias]['modified']);
        if (empty($this->data[$this->alias])) {
            // Teoreticky jsme tím mohli odebrat všechny klíče z pole s klíčem '$this->alias', takže ten musíme odebrat, Cake si s tím neporadí
            unset($this->data[$this->alias]);
        }

        // Chyba v logice Model::save() -> pokud v $this->data->id něco bylo, a volal se Model::exists(), a následně se $this->id = null,
        // nebo $this->id = $jinyId, stále v interním záznamu zůstává původní hodnota exists. Volání Model::create() to sice vyresetuje,
        // ale to zase vytváří nechtěné defaulty do $this->data
        if (isset($this->__exists, $this->existsId)) {
            $existsId = is_int($this->existsId) ? (string) $this->existsId : $this->existsId;
            $id = is_int($this->id) ? (string) $this->id : $this->id;
            if ($existsId !== $id) {
                $this->__exists = null;
            }
        }

        // Vložíme vyfiltrovaná data, ne původní
        $return = parent::save($this->data, $validate, $fieldList);
        if (is_array($return) && $this->id) {
            // Pokud je úspěšný save, doplníme hodnotu primárního klíče, u CREATE se chybně nedoplňuje
            $return[$this->alias][$this->primaryKey] = $this->id;
        }
        return $this->lastSaveResult = $return;
    }


    /**
     * Přepisuje původní metodu, pouze navíc uloží do $this->existsId idčko záznamu, pro který se existence vlastně zjišťovala + oprava empty
     * To se použije na správné dovyresetování $this->__exists v přepsaném save()
     * @param $reset
     * @return bool
     */
    function exists($reset = false)
    {
        if (is_array($reset)) {
            extract($reset, EXTR_OVERWRITE);
        }
        $id = $this->getID();
        if ($id === false || $this->useTable === false) {
            return false;
        }
        if ($this->__exists !== null && $reset !== true) { // PREPSANO
            return $this->__exists;
        }
        $conditions = array($this->alias . '.' . $this->primaryKey => $id);
        $query = array('conditions' => $conditions, 'recursive' => -1, 'callbacks' => false);

        if (is_array($reset)) {
            $query = array_merge($query, $reset);
        }
        $this->existsId = $id;
        return $this->__exists = ($this->find('count', $query) > 0);
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


    /**
     * @param E[] $entities
     * @param ?array $contains
     */
    private function addReferencedEntities(array &$entities, ?array $contains, array $params): void
    {
        if ( ! $entities) {
            return;
        }

        if ( ! $contains = $this->getContains($contains)) {
            return;
        }
        $containedModels = array_keys($contains);

        /** @var class-string<E> $entityClass */
        $entityClass = $this->getEntityClass();

        // První foreach najde všechny reference (aka parent_id) do vlastní tabulky, vytvoří entity těch co ještě nejsou a přidá je do $entities
        /** @var \ReflectionProperty $property */
        foreach ($entityClass::getPropertiesOfReferencedEntities() as $keyPropertyName => $property) {
            $referencedEntityClass = $property->getType()->getName();
            $modelClass = $referencedEntityClass::getModelClass();
            // Musí být v contains a zde řešíme jenom rekurzi do vlastní tabulky
            if ( ! in_array($modelClass, $containedModels, true) || $modelClass !== static::class) {
                continue;
            }
            $refIds = [];
            $fetchedIds = [];
            foreach ($entities as $entity) {
                $refIds[] = $entity->{$keyPropertyName} ?? null;
                $fetchedIds[] = $entity->getPrimary();
            }
            $refIds = $this->filterIds($refIds);
            $fetchedIds = $this->filterIds($fetchedIds);
            $refIds = array_diff($refIds, $fetchedIds);

            if ($refIds) {
                $refIdsList = implode(',', $refIds);
                $parentsIds = $this->query("
                        WITH RECURSIVE ascendants AS (
                            SELECT id, parent_id
                            FROM $this->useTable
                            WHERE id IN ($refIdsList)
                        
                            UNION ALL
                        
                            SELECT t.id, t.parent_id
                            FROM $this->useTable t
                            INNER JOIN ascendants ON t.id = ascendants.parent_id
                        )
                        SELECT id
                        FROM ascendants
                    ");
                $refIds = $this->filterIds(Arrays::flatten($parentsIds));
                $refIds = array_diff($refIds, $fetchedIds);
            }
            if ($refIds) {
                $params['conditions'] = ["$this->primaryKey" => $refIds];
                $entities += $this->findEntities($params, []);
            }
        }

        // Druhý foreach přiřadí všechny reference do vlastní tabulky
        /** @var \ReflectionProperty $property */
        foreach ($entityClass::getPropertiesOfReferencedEntities() as $keyPropertyName => $property) {
            $referencedEntityClass = $property->getType()->getName();
            $modelClass = $referencedEntityClass::getModelClass();
            // Musí být v contains a zde řešíme jenom rekurzi do vlastní tabulky
            if ( ! in_array($modelClass, $containedModels, true) || $modelClass !== static::class) {
                continue;
            }
            foreach ($entities as $entity) {
                if (isset($entity->{$keyPropertyName})) {
                    if (isset($entities[$entity->{$keyPropertyName}])) {
                        $property->setValue($entity, $entities[$entity->{$keyPropertyName}]);
                        continue;
                    }
                }
                if ($property->getType()->allowsNull()) {
                    $property->setValue($entity, null);
                }
            }
        }
        // Třetí foreach přiřadí všechny ostatní reference
        /** @var \ReflectionProperty $property */
        foreach ($entityClass::getPropertiesOfReferencedEntities() as $keyPropertyName => $property) {
            $referencedEntityClass = $property->getType()->getName();
            $modelClass = $referencedEntityClass::getModelClass();
            // Musí být v contains a řešíme vše MIMO rekurzi do vlastní  tabulky
            if ( ! in_array($modelClass, $containedModels, true) || $modelClass === static::class) {
                continue;
            }
            $Model = $this->getModel($modelClass);
            if ( ! in_array(EntityAppModel::class, Reflection::getUsedTraits(get_class($Model)))) {
                throw new \InvalidArgumentException("Model '$modelClass' included in \$contains parameter is not instance of EntityAppModel.");
            }

            $refIds = [];
            $associableEntities = [];
            foreach ($entities as $entity) {
                if ($property->getType()->allowsNull()) {
                    $property->setValue($entity, null);
                }
                $refId = $entity->{$keyPropertyName} ?? null;
                if ($refId !== null) {
                    $associableEntities[] = $entity;
                    $refIds[] = $refId;
                }
            }

            $refIds = $this->filterIds($refIds);

            $refContains = null;
            if ($params = is_array($contains[$modelClass]) ? $contains[$modelClass] : []) {
                $refContains = $params['contains'] ?? null;
                unset($params['contains']);
            }
            $params['conditions'] = [$Model->primaryKey => $refIds];

            $refEntities = $refIds
                ? $Model->findEntities($params, $refContains)
                : [];

            foreach ($associableEntities as $entity) {
                if (isset($refEntities[$entity->{$keyPropertyName}])) {
                    $property->setValue($entity, $refEntities[$entity->{$keyPropertyName}]);
                }
            }
        }
    }


    /**
     * @param E[] $entities
     * @param ?array $contains
     */
    private function addRelatedEntities(array $entities, ?array $contains): void
    {
        if ( ! $entities) {
            return;
        }

        if ( ! $contains = $this->getContains($contains)) {
            return;
        }
        $containedModels = array_keys($contains);

        /** @var class-string<E> $entityClass */
        $entityClass = $this->getEntityClass();
        /** @var Relation $relation */
        foreach ($entityClass::getPropertiesOfRelatedEntities() as $propertyName => $relation) {
            $modelClass = $relation->relatedEntityClass::getModelClass();
            if ( ! in_array($modelClass, $containedModels, true)) {
                continue;
            }
            $Model = $this->getModel($modelClass);
            if ( ! in_array(EntityAppModel::class, Reflection::getUsedTraits(get_class($Model)))) {
                throw new \InvalidArgumentException("Model '$modelClass' included in \$contains parameter is not instance of EntityAppModel.");
            }

            $ids = [];
            foreach ($entities as $entity) {
                $entity->{$propertyName} = [];
                $ids[] = $entity->getPrimary();
            }

            $ids = $this->filterIds($ids);

            $relatedContains = null;
            if ($params = is_array($contains[$modelClass]) ? $contains[$modelClass] : []) {
                $relatedContains = $params['contains'] ?? null;
                unset($params['contains']);
                if (isset($params['fields']) && is_array($params['fields']) && ! in_array($relation->column, $params['fields'], true)) {
                    $params['fields'][] = $relation->column;
                }
            }
            $params['conditions'] = [$relation->column => $ids];

            $relatedEntities = $ids
                ? $Model->findEntities($params, $relatedContains)
                : [];

            $relatedProperty = Strings::fromSnakeCaseToCamelCase($relation->column);
            /** @var E $relatedEntity */
            foreach ($relatedEntities as $relatedEntity) {
                $entities[$relatedEntity->{$relatedProperty}]->{$propertyName}[$relatedEntity->getPrimary()] = $relatedEntity;
            }
        }
    }

    private function getContains(?array $contains): array
    {
        $normalizedContains = [];
        foreach ($contains ?? $this->contains as $key => $value) {
            if (is_array($value) || $value === null) {
                $normalizedContains[$key] = $value;
            } elseif (is_string($value)) {
                $normalizedContains[$value] = null;
            }
        }

        return $normalizedContains;
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