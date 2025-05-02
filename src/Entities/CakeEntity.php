<?php

namespace Cesys\CakeEntities\Entities;

use Cesys\Utils\Strings;
use Cesys\Utils\Reflection;

require_once __DIR__ . '/Relation.php';

abstract class CakeEntity
{
    /**
     * @var \ReflectionProperty[][]
     */
    private static array $properties = [];

    /**
     * @return int|string|null
     */
    public function getPrimary()
    {
        $rp = static::getProperties()[static::getPrimaryPropertyName()];
        if ( ! $rp->isInitialized($this)) {
            return null;
        }
        return $rp->getValue($this);
    }

    public function setPrimary($value)
    {
        $rp = static::getProperties()[static::getPrimaryPropertyName()];
        $rp->setValue($this, $value);
    }


    public function toDbArray(): array
    {
        $data = [];
        foreach (static::getProperties() as $propertyName => $property) {
            if (in_array($propertyName, static::getExcludedFromDbArray(), true)) {
                continue;
            }
            if ( ! $property->isInitialized($this)) {
                continue;
            }
            $column = Strings::fromCamelCaseToSnakeCase($propertyName);
            $value = $this->{$propertyName};
            $type = $property->getType();
            if ( ! $type || $value === null) {
                $data[$column] = $value;
                continue;
            }
            $typeName = $type->getName();
            if (is_a($typeName, \DateTime::class, true)) {
                $data[$column] = $value->format('Y-m-d H:i:s');
            } elseif ($typeName === 'bool') {
                $data[$column] = (int) $value;
            } else {
                $data[$column] = $value;
            }
        }

        return $data;
    }

    /**
     * @param array $data
     * @return static
     */
    public static function createFromDbArray(array $data)
    {
        $entity = new static();
        foreach ($data as $column => $value) {
            $propertyName = Strings::fromSnakeCaseToCamelCase($column);
            if ( ! array_key_exists($propertyName, static::getProperties())) {
                continue;
            }

            $type = static::getProperties()[$propertyName]->getType();
            if ($type) {
                $typeName = $type->getName();
                if (is_a($typeName, \DateTime::class, true) && is_string($value)) {
					if (strpos($value, ' ') === false) {
						$entity->{$propertyName} = $typeName::createFromFormat('!Y-m-d', $value);
					} else {
						$entity->{$propertyName} = $typeName::createFromFormat('Y-m-d H:i:s', $value);
					}
                    continue;
                }
            }

            $entity->{$propertyName} = $value;
        }

        return $entity;
    }

    public static function &getProperties(): array
    {
        if (isset(self::$properties[static::class])) {
            return self::$properties[static::class];
        }
        $result = [];
        $rc = Reflection::getReflectionClass(static::class);
        foreach ($rc->getProperties() as $property) {
            // todo is static?
            if ($property->isPrivate() || $property->isProtected() || in_array($property->getName(), static::getExcludedFromProperties(), true)) {
                continue;
            }
            if ($type = $property->getType()) {
                if ( ! in_array($type->getName(), ['int', 'float', 'string', 'bool'],true) && ! is_a($type->getName(), \DateTime::class, true)) {
                    continue;
                }
            }
            $result[$property->getName()] = $property;
        }
        self::$properties[static::class] = $result;
        return self::$properties[static::class];
    }

    /**
     *
     * @return \ReflectionProperty[]
     */
    public static function getPropertiesOfReferencedEntities(): array
    {
        $result = [];
        foreach (Reflection::getReflectionPropertiesOfClass(static::class) as $property) {
            if ($type = $property->getType()) {
                if (is_a($type->getName(), self::class, true)) {
                    $keyPropertyName = $property->getName() . 'Id';
                    if (array_key_exists($keyPropertyName, static::getProperties())) {
                        $result[$keyPropertyName] = $property;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @return Relation[]
     */
    public static function getPropertiesOfRelatedEntities(): array
    {
        $result = [];
        $rc = Reflection::getReflectionClass(static::class);
        foreach ($rc->getProperties() as $property) {
            if ( ! $type = $property->getType()) {
                continue;
            }
            if ($type->getName() !== 'array') {
                continue;
            }

            if ($annotation = Reflection::parseAnnotation($property, 'var')) {
                [$annotationType, $column] = preg_split('/[\s\t]+/', trim($annotation), -1, PREG_SPLIT_NO_EMPTY);
                if ( ! $annotationType || ! $column) {
                    continue;
                }
                $simpleType = preg_replace('/^(\w+).*$/', '$1', $annotationType);
                if ( ! $simpleType) {
                    continue;
                }
                $relatedEntityClass = Reflection::expandClassName($simpleType, $rc);
                if ( ! is_a($relatedEntityClass, self::class, true)) {
                    continue;
                }
                $result[$property->getName()] = new Relation($property, $column, $relatedEntityClass);
            }
        }
        return $result;
    }

    public static function getExcludedFromDbArray(): array
    {
        return ['created', 'modified', 'created_by', 'modified_by'];
    }

    public static function getExcludedFromProperties(): array
    {
        return [];
    }

    public static function getPrimaryPropertyName(): string
    {
        return 'id';
    }

    public static function getModelClass(): string
    {
        return Reflection::getReflectionClass(static::class)->getShortName();
    }

}