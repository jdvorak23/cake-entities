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

    protected array $entities = [];

    private array $defaults;

    private string $entityClass;

    /**
     * todo popis
     * @return array
     */
    public static function getDefaultContains(): array
    {
        return [];
    }


    /**
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
     * @return bool
     */
    public function saveEntity(CakeEntity $entity, array $appendData = [], bool $validate = true, array $fieldList = []): bool
    {
        $data = $entity->toDbArray();
        $now = date('Y-m-d H:i:s');

        $hasCreated = false;
        if ($entity->getPrimary() === null) {
            if ($created = $this->schema('created')) {
                if ($created['type'] === 'datetime') {
                    $data['created'] = $now;
                    $hasCreated = true;
                }
            }
            $this->create();
        }

        $hasModified = false;
        if ($modified = $this->schema('modified')) {
            if ($modified['type'] === 'datetime') {
                $data['modified'] = $now;
                $hasModified = true;
            }
        }

        $result = $this->save([$this->alias => array_merge($data, $appendData)], $validate, $fieldList);
        
        if ($result) {
            if ($entity->getPrimary() === null) {
                $entity->setPrimary($this->id);
            }

            if ($hasCreated && $property = $entity::getProperties()['created'] ?? null) {
                if ($property->getType() instanceof \ReflectionNamedType && is_a($property->getType()->getName(), \DateTime::class, true) ) {
                    $entity->created = $property->getType()->getName()::createFromFormat('Y-m-d H:i:s', $now);
                }
            }

            if ($hasModified && $property = $entity::getProperties()['modified'] ?? null) {
                if ($property->getType() instanceof \ReflectionNamedType && is_a($property->getType()->getName(), \DateTime::class, true) ) {
                    $entity->modified = $property->getType()->getName()::createFromFormat('Y-m-d H:i:s', $now);
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
        foreach ($contains ?? static::getDefaultContains() as $key => $value) {
            if (is_array($value)) {
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