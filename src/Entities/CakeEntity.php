<?php

namespace Cesys\CakeEntities\Entities;

use Nette\Utils\Reflection;

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
            $column = static::fromCamelCaseToSnakeCase($propertyName);
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
            $propertyName = static::fromSnakeCaseToCamelCase($column);
            if ( ! array_key_exists($propertyName, static::getProperties())) {
                continue;
            }

            $type = static::getProperties()[$propertyName]->getType();
            if ($type) {
                $typeName = $type->getName();
                if (is_a($typeName, \DateTime::class, true) && is_string($value)) {
                    $entity->{$propertyName} = $typeName::createFromFormat('Y-m-d H:i:s', $value);
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
        $rc = new \ReflectionClass(static::class);
        foreach ($rc->getProperties() as $property) {
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
        //todo použít reflection helper
        $rc = new \ReflectionClass(static::class);
        foreach ($rc->getProperties() as $property) {
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
        $rc = new \ReflectionClass(static::class);
        foreach ($rc->getProperties() as $property) {
            if ( ! $type = $property->getType()) {
                continue;
            }
            if ($type->getName() !== 'array') {
                continue;
            }

            if ($annotation = static::parseAnnotation($property, 'var')) {
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

    /**
     * TODO Přesunout do reflection helperu
     * @param \ReflectionProperty $ref
     * @param string $name
     * @return string|null
     */
    public static function parseAnnotation(\ReflectionProperty $ref, string $name): ?string
    {
        $re = '#[\s*]@' . preg_quote($name, '#') . '(?=\s|$)[ \t]+(.+)?#';
        if ($ref->getDocComment() && preg_match($re, trim($ref->getDocComment(), '/*'), $m)) {
            return $m[1] ?? '';
        }

        return null;
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
        // todo ReflectionHelper
        $rc = new \ReflectionClass(static::class);
        return $rc->getShortName();
    }

    /**
     * todo použít utils
     * @param string $string
     * @return string
     */
    public static function fromCamelCaseToSnakeCase(string $string): string
    {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $string, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }

    /**
     * todo použít utils
     * @param string $string
     * @return string
     */
    public static function fromSnakeCaseToCamelCase(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }
}