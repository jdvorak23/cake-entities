<?php

namespace Cesys\CakeEntities\Model\Entities;

use Cesys\CakeEntities\Entities\Relation;
use Cesys\Utils\Reflection;
use Cesys\Utils\Strings;

abstract class CakeEntity
{
    /**
     * @var string[][]
     */
    protected static array $modelClasses = [];

    /**
     * @var array[]
     */
    protected static array $excludedFormDbArrays = [];
    /**
     * @var \ReflectionProperty[][]
     */
    private static array $properties = [];

    /**
     * @var ColumnProperty[][]
     */
    private static array $columnProperties = [];

    /**
     * @var ColumnProperty[][]
     */
    private static array $columnPropertiesByColumn = [];

    /**
     * @var \ReflectionProperty[][]
     */
    private static array $propertiesOfReferencedEntities = [];

    /**
     * @var Relation[][]
     */
    private static array $propertiesOfRelatedEntities = [];



    /**
     * @return int|string|null
     */
    public function getPrimary()
    {
        // todo err?
        $primaryColumnProperty = static::getColumnProperties()[static::getPrimaryPropertyName()];;
        if ( ! $primaryColumnProperty->property->isInitialized($this)) {
            return null;
        }
        return $primaryColumnProperty->property->getValue($this);
    }


    /**
     * @param int|string $value
     * @return void
     */
    public function setPrimary($value)
    {
        $primaryColumnProperty = static::getColumnProperties()[static::getPrimaryPropertyName()];
        $primaryColumnProperty->property->setValue($this, $value);
    }


    public function toDbArray(): array
    {
        $data = [];
        foreach (static::getColumnProperties() as $propertyName => $columnProperty) {
            if (in_array($propertyName, static::getExcludedFromDbArray(), true)) {
                continue;
            }
            if ( ! $columnProperty->property->isInitialized($this)) {
                continue;
            }

            $value = $this->{$propertyName};
            $type = $columnProperty->property->getType();
            if ( ! $type || $value === null) {
                $data[$columnProperty->column] = $value;
                continue;
            }
            $typeName = $type->getName();
            if (is_a($typeName, \DateTime::class, true)) {
                $data[$columnProperty->column] = $columnProperty->isDateOnly ? $value->format('Y-m-d') : $value->format('Y-m-d H:i:s');
            } elseif ($typeName === 'bool') {
                $data[$columnProperty->column] = (int) $value;
            } else {
                $data[$columnProperty->column] = $value;
            }
        }

        return $data;
    }

	public function appendFromDbArray(array $data)
	{
        self::appendToEntity($data, $this);
	}


    /**
     * @param array $data
     * @return static
     */
    public static function createFromDbArray(array $data)
    {
        $entity = new static();
        self::appendToEntity($data, $entity);
        return $entity;
    }


    /**
     * @param array $data
     * @param CakeEntity $entity
     * @return void
     */
    private static function appendToEntity(array $data, CakeEntity $entity): void
    {
        foreach ($data as $column => $value) {
            if ( ! array_key_exists($column, static::getColumnPropertiesByColumn())) {
                continue;
            }
            $columnProperty = static::getColumnPropertiesByColumn()[$column];

            $type = $columnProperty->property->getType();
            if ($type) {
                $typeName = $type->getName();
                if (is_a($typeName, \DateTime::class, true) && is_string($value)) {
                    if (strpos($value, ' ') === false) {
                        $entity->{$columnProperty->propertyName} = $typeName::createFromFormat('!Y-m-d', $value);
                    } else {
                        $entity->{$columnProperty->propertyName} = $typeName::createFromFormat('Y-m-d H:i:s', $value);
                    }
                    continue;
                }
            }

            $entity->{$columnProperty->propertyName} = $value;
        }
    }


    /**
     * @return string[]
     */
    public static function getExcludedFromDbArray(): array
    {
        return static::$excludedFormDbArrays[static::class] ??= ['created', 'modified', 'createdBy', 'modifiedBy'];
    }


    /**
     * @param array $excludedFromDbArray
     * @return string[] původní $excludedFromDbArray
     */
    public static function setExcludedFromDbArray(array $excludedFromDbArray): array
    {
        $originalExcludedFromDbArray = static::getExcludedFromDbArray();
        static::$excludedFormDbArrays[static::class] = $excludedFromDbArray;
        return $originalExcludedFromDbArray;
    }


    /**
     * @return string
     */
    public static function getModelClass(): string
    {
        return static::$modelClasses[static::class] ??= Reflection::getReflectionClass(static::class)->getShortName();
    }


    /**
     * @param string $modelClass
     * @return string původní $modelClass
     */
    public static function setModelClass(string $modelClass): string
    {
        $originalClass = static::getModelClass();
        static::$modelClasses[static::class] = $modelClass;
        return $originalClass;
    }


    public static function getExcludedFromProperties(): array
    {
        return [];
    }


    public static function getPrimaryPropertyName(): string
    {
        return 'id';
    }


    public static function &getProperties(): array
    {
        if (isset(self::$properties[static::class])) {
            return self::$properties[static::class];
        }
        $result = [];
        foreach (Reflection::getReflectionPropertiesOfClass(static::class) as $property) {
            if ($property->isStatic() || $property->isPrivate() || $property->isProtected() || in_array($property->getName(), static::getExcludedFromProperties(), true)) {
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
     * @return ColumnProperty[]
     */
    public static function &getColumnProperties(): array
    {
        if (isset(self::$columnProperties[static::class])) {
            return self::$columnProperties[static::class];
        }
        $result = [];
        foreach (Reflection::getReflectionPropertiesOfClass(static::class) as $property) {
            if ($property->isStatic() || $property->isPrivate() || $property->isProtected() || in_array($property->getName(), static::getExcludedFromProperties(), true)) {
                continue;
            }
            if ($type = $property->getType()) {
                if ( ! in_array($type->getName(), ['int', 'float', 'string', 'bool'],true) && ! is_a($type->getName(), \DateTime::class, true)) {
                    continue;
                }
            }
            $columnProperty = new ColumnProperty();
            $columnProperty->entityClass = static::class;
            $columnProperty->propertyName = $property->getName();
            // Todo povolit nějak přes anotaci jinak než fromCamelCaseToSnakeCase?
            $columnProperty->column = Strings::fromCamelCaseToSnakeCase($columnProperty->propertyName);
            $columnProperty->property = $property;
            $result[$columnProperty->propertyName] = $columnProperty;
        }

        self::$columnProperties[static::class] = $result;
        return self::$columnProperties[static::class];
    }

    /**
     * @return ColumnProperty[]
     */
    public static function &getColumnPropertiesByColumn(): array
    {
        if (isset(self::$columnPropertiesByColumn[static::class])) {
            return self::$columnPropertiesByColumn[static::class];
        }
        $result = [];
        foreach (static::getColumnProperties() as $columnProperty) {
            $result[$columnProperty->column] = $columnProperty;
        }

        self::$columnPropertiesByColumn[static::class] = $result;
        return self::$columnPropertiesByColumn[static::class];
    }

    /**
     *
     * @return \ReflectionProperty[]
     */
    public static function &getPropertiesOfReferencedEntities(): array
    {
        if (isset(self::$propertiesOfReferencedEntities[static::class])) {
            return self::$propertiesOfReferencedEntities[static::class];
        }
        $result = [];
        foreach (Reflection::getReflectionPropertiesOfClass(static::class) as $property) {
            if ($property->isStatic() || $property->isPrivate() || $property->isProtected()) {
                continue;
            }
            if ($type = $property->getType()) {
                if (is_a($type->getName(), self::class, true)) {
                    if ($annotation = Reflection::parseAnnotation($property, 'var')) {
                        $parsed = preg_split('/[\s\t]+/', trim($annotation), -1, PREG_SPLIT_NO_EMPTY);
                        if (count($parsed) > 1) {
                            [$annotationType, $keyPropertyName] = $parsed;
                            if ($annotationType && $keyPropertyName && array_key_exists($keyPropertyName, static::getColumnProperties())) {
                                $result[$keyPropertyName] = $property;
                                continue;
                            }
                        }

                    }
                    $keyPropertyName = $property->getName() . 'Id';
                    if (array_key_exists($keyPropertyName, static::getColumnProperties())) {
                        $result[$keyPropertyName] = $property;
                    }
                }
            }
        }
        self::$propertiesOfReferencedEntities[static::class] = $result;
        return self::$propertiesOfReferencedEntities[static::class];
    }

    /**
     * @return Relation[]
     */
    public static function &getPropertiesOfRelatedEntities(): array
    {
        if (isset(self::$propertiesOfRelatedEntities[static::class])) {
            return self::$propertiesOfRelatedEntities[static::class];
        }
        $result = [];
        foreach (Reflection::getReflectionPropertiesOfClass(static::class) as $property) {
            if ( ! $type = $property->getType()) {
                continue;
            }
            if ($type->getName() !== 'array') {
                continue;
            }

            if ($annotation = Reflection::parseAnnotation($property, 'var')) {
                $parsed = preg_split('/[\s\t]+/', trim($annotation), -1, PREG_SPLIT_NO_EMPTY);
                if (count($parsed) < 2) {
                    continue;
                }
                [$annotationType, $column] = $parsed;
                if ( ! $annotationType || ! $column) {
                    continue;
                }
                $simpleType = preg_replace('/^(\w+).*$/', '$1', $annotationType);
                if ( ! $simpleType) {
                    continue;
                }
                $relatedEntityClass = Reflection::expandClassName($simpleType, Reflection::getReflectionClass(static::class));
                if ( ! is_a($relatedEntityClass, self::class, true)) {
                    continue;
                }
                $result[$property->getName()] = new Relation($property, $column, $relatedEntityClass);
            }
        }
        self::$propertiesOfRelatedEntities[static::class] = $result;
        return self::$propertiesOfRelatedEntities[static::class];
    }
}