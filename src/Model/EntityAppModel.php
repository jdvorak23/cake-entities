<?php

namespace Cesys\CakeEntities\Model;

use Cesys\CakeEntities\Entities\CakeEntity;
use Cesys\CakeEntities\Entities\Relation;
use Cesys\Utils\Reflection;
use Cesys\Utils\Strings;

/**
 * @template T of CakeEntity
 */
trait EntityAppModel
{
    private array $entities = [];

    /**
     * @param array $params
     * @param array|null $contains
     * @return ?T
     */
    public function findEntity(array $params = [], ?array $contains = null)
    {
        $params['limit'] = 1;
        $entities = $this->findEntities($params, $contains);
        return $entities ? current($entities) : null;
    }

    /**
     * @param array $params
     * @param array|null $contains
     * @return T[]
     */
    public function findEntities(array $params = [], ?array $contains = null): array
    {
        $params['recursive'] = -1;
        if (isset($params['fields']) && is_array($params['fields']) && ! in_array($this->primaryKey, $params['fields'], true)) {
            $params['fields'][] = $this->primaryKey;
        }
        $entitiesData = $this->find('all', $params);
        $entityClass = $this->getEntityClass();
        $entities = [];
        foreach ($entitiesData as $entityData) {
            /** @var CakeEntity $entity */
            $entity = $entityClass::createFromDbArray($entityData[$this->alias]);
            $this->entities[$entity->getPrimary()] = $entity;
            $entities[$entity->getPrimary()] = $entity;
        }
        $this->addReferencedEntities($entities, $contains);
        $this->addRelatedEntities($entities, $contains);
        return $entities;
    }

    /**
     * @param CakeEntity[] $entities
     * @param ?array $contains
     */
    private function addReferencedEntities(array $entities, ?array $contains): void
    {
        if ( ! $entities) {
            return;
        }

        if ( ! $contains = $this->getContains($contains)) {
            return;
        }
        $containedModels = array_keys($contains);

        /** @var \ReflectionProperty $property */
        foreach (($this->getEntityClass())::getPropertiesOfReferencedEntities() as $keyPropertyName => $property) {
            $referencedEntityClass = $property->getType()->getName();
            $modelClass = $referencedEntityClass::getModelClass();
            if ( ! in_array($modelClass, $containedModels, true)) {
                continue;
            }
            $Model = $this->getModel($modelClass);
            if ( ! in_array(EntityAppModel::class, Reflection::getUsedTraits(static::class))) {
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
     * @param CakeEntity[] $entities
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

        /** @var Relation $relation */
        foreach (($this->getEntityClass())::getPropertiesOfRelatedEntities() as $propertyName => $relation) {
            $modelClass = $relation->relatedEntityClass::getModelClass();
            if ( ! in_array($modelClass, $containedModels, true)) {
                continue;
            }
            $Model = $this->getModel($modelClass);
            if ( ! in_array(EntityAppModel::class, Reflection::getUsedTraits(static::class))) {
                throw new \InvalidArgumentException("Model '$modelClass' included in \$contains parameter is not instance of EntityAppModel.");
            }

            $ids = [];
            foreach ($entities as $entity) {
                $entity->{$propertyName} = [];
                $ids[] = $entity->getPrimary();
            }

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
            /** @var T $relatedEntity */
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
     * @param $id
     * @param bool $useCache
     * @return ?T
     */
    public function getEntity($id, bool $useCache = true)
    {
        if ($id === null) {
            return null;
        }

        if ($useCache && isset($this->entities[$id])) {
            return $this->entities[$id];
        }

        return $this->findEntity(['conditions' => [$this->primaryKey => $id]]);
    }

    public function getEntityClass(): string
    {
        $classWithoutNamespace = static::class;
		// todo podle názvu db
		$subNamespace = ucfirst(Strings::fromSnakeCaseToCamelCase($this->useDbConfig));
        return "\\Cesys\\CakeEntities\\Entities\\$subNamespace\\$classWithoutNamespace";
    }

    public static function getDefaultContains(): array
    {
        return [];
    }

}