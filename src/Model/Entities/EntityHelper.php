<?php

namespace Cesys\CakeEntities\Model\Entities;

use Cesys\CakeEntities\Model\EntityAppModelTrait;
use Cesys\Utils\Arrays;
use Cesys\Utils\Reflection;
use Cesys\Utils\Strings;

/**
 * @template E of CakeEntity
 */
class EntityHelper
{
    private static array $cache = [];

	public static function toDbArray(CakeEntity $entity): array
	{
		$data = [];
		foreach (static::getColumnProperties(get_class($entity)) as $propertyName => $columnProperty) {
			if (in_array($propertyName, $entity::getExcludedFromDbArray(), true)) {
				continue;
			}
			if ( ! $columnProperty->property->isInitialized($entity)) {
				continue;
			}

			$data[$columnProperty->column] = static::getPropertyDbValue($entity, $columnProperty);
		}

		return $data;
	}


	public static function appendFromDbArray(CakeEntity $entity, array $data)
	{
		$entityClass = get_class($entity);
		$modelClass = $entityClass::getModelClass();
		$data = $data[$modelClass] ?? $data;
		foreach ($data as $column => $value) {
			$columnProperty = static::getColumnPropertiesByColumn($entityClass)[$column] ?? null;
			if ( ! $columnProperty) {
				continue;
			}
			static::appendFromDbValue($entity, $columnProperty, $value);
		}
	}


	/**
	 * @param CakeEntity $entity
	 * @param ColumnProperty|string $column
	 * @param int|float|string|null $value
	 * @return void
	 */
	public static function appendFromDbValue(CakeEntity $entity, $column, $value)
	{
		$columnProperty = self::getColumnProperty($entity, $column);
		$type = $columnProperty->property->getType();
		if ($type) {
			$typeName = $type->getName();
			if (is_a($typeName, \DateTime::class, true) && is_string($value)) {
				if (strpos($value, ' ') === false) {
					$entity->{$columnProperty->propertyName} = $typeName::createFromFormat('!Y-m-d', $value);
				} else {
					$entity->{$columnProperty->propertyName} = $typeName::createFromFormat('Y-m-d H:i:s', $value);
				}
				return;
			}
		}

		// To pak padá pokud se vkládají hodnoty z formuláře, musí se převést prázdný řetězec na null
		// Takže na to seru a obecně, pokud to je null-possible property a hodnota je '', uloží se null
		if ($value === '' && ( ! $type || $type->allowsNull())) {
			$entity->{$columnProperty->propertyName} = null;
		} else {
			$entity->{$columnProperty->propertyName} = $value;
		}
	}

	/**
	 * @param CakeEntity $entity
	 * @param ColumnProperty|string $property
	 * @param bool $throwOnUninitialized
	 * @return int|float|string|null
	 */
	public static function getPropertyDbValue(CakeEntity $entity, $property, bool $throwOnUninitialized = false)
	{
		$entityClass = get_class($entity);
		if (is_string($property)) {
			$columnProperty = static::getColumnProperties($entityClass)[$property] ?? null;
			if ( ! $columnProperty) {
				throw new \InvalidArgumentException("Property '$property' does not exist in entity '$entityClass', or is not properly bind to the column.");
			}
		} elseif ($property instanceof ColumnProperty) {
			$columnProperty = $property;
		} else {
			$columnPropertyClass = ColumnProperty::class;
			throw new \InvalidArgumentException("Argument \$property must me either 'string' or '$columnPropertyClass' class.");
		}

		if ( ! $columnProperty->property->isInitialized($entity)) {
			if ($throwOnUninitialized) {
				throw new \Error("Typed property $entityClass::\${$columnProperty->propertyName} must not be accessed before initialization.");
			}
			return null;
		}

		$value = $columnProperty->property->getValue($entity);
		$type = $columnProperty->property->getType();
		if ( ! $type || $value === null) {
			return $value;
		}

		$typeName = $type->getName();
		if (is_a($typeName, \DateTime::class, true)) {
			return $columnProperty->isDateOnly ? $value->format('Y-m-d') : $value->format('Y-m-d H:i:s');
		} elseif ($typeName === 'bool') {
			return (int) $value;
		}

		return $value;
	}


	/**
	 * @param CakeEntity $entity
	 * @param ColumnProperty|string $column
	 * @return int|float|string|null
	 */
	public static function getColumnDbValue(CakeEntity $entity, $column)
	{
		$columnProperty = self::getColumnProperty($entity, $column);
		return static::getPropertyDbValue($entity, $columnProperty);
	}


	/**
     * @param class-string<E> $entityClass
     * @return ColumnProperty[]
     */
    public static function &getColumnProperties(string $entityClass): array
    {
        if (isset(self::$cache[__METHOD__][$entityClass])) {
            return self::$cache[__METHOD__][$entityClass];
        }

		if ( ! is_a($entityClass, CakeEntity::class, true)) {
			$class = CakeEntity::class;
			throw new \InvalidArgumentException("'$entityClass' is not subclass of '$class'.");
		}

		$model = static::getModelStatic($entityClass::getModelClass());
		if ( ! in_array(EntityAppModelTrait::class, Reflection::getUsedTraits(get_class($model)))) {
			$modelClass = get_class($model);
			throw new \InvalidArgumentException("Model '$modelClass' included in \$contains parameter is not instance of EntityAppModel.");
		}
		$schema = $model->schema();

        $result = [];
        foreach (Reflection::getReflectionPropertiesOfClass($entityClass) as $property) {
            if ($property->isStatic() || $property->isPrivate() || $property->isProtected() || in_array($property->getName(), $entityClass::getExcludedFromProperties(), true)) {
                continue;
            }
            if ($type = $property->getType()) {
                if ( ! in_array($type->getName(), ['int', 'float', 'string', 'bool'],true) && ! is_a($type->getName(), \DateTime::class, true)) {
                    continue;
                }
            }

            $columnProperty = new ColumnProperty();
            $columnProperty->entityClass = $entityClass;
            $columnProperty->propertyName = $property->getName();
            // Todo povolit nějak přes anotaci jinak než fromCamelCaseToSnakeCase?
            $columnProperty->column = Strings::fromCamelCaseToSnakeCase($columnProperty->propertyName);
            $columnProperty->property = $property;
			if ($columnSchema = $schema[$columnProperty->column] ?? null) {
				if ($columnSchema['type'] === 'date') {
					$columnProperty->isDateOnly = true;
				}
			} else {
				throw new \LogicException("Property '$columnProperty->propertyName' of class '$entityClass' have no column '$columnProperty->column' in table '$model->useTable'.");
			}
            $result[$columnProperty->propertyName] = $columnProperty;
        }

        self::$cache[__METHOD__][$entityClass] = $result;
        return self::$cache[__METHOD__][$entityClass];
    }


    /**
     * @param class-string<E> $entityClass
     * @return ColumnProperty[]
     */
    public static function &getColumnPropertiesByColumn(string $entityClass): array
    {
        if (isset(self::$cache[__METHOD__][$entityClass])) {
            return self::$cache[__METHOD__][$entityClass];
        }
        $result = [];
        foreach (static::getColumnProperties($entityClass) as $columnProperty) {
            $result[$columnProperty->column] = $columnProperty;
        }

        self::$cache[__METHOD__][$entityClass] = $result;
        return self::$cache[__METHOD__][$entityClass];
    }

	/**
	 * @param class-string<E> $entityClass
	 * @param array $containedModels
	 * @return RelatedProperty[]
	 */
	public static function getPropertiesOfOtherEntities(string $entityClass, array $containedModels): array
	{
		$sortedRelatedProperties = array_fill_keys($containedModels, []);

		$relatedProperties = array_merge(
			static::getPropertiesOfOtherReferencedEntities($entityClass),
			static::getPropertiesOfOtherRelatedEntities($entityClass)
		);

		foreach ($relatedProperties as $propertyName => $referencedProperty) {
			$modelClass = $referencedProperty->relatedColumnProperty->entityClass::getModelClass();
			if ( ! array_key_exists($modelClass, $sortedRelatedProperties)) {
				// Musí být v modelech
				continue;
			}
			$sortedRelatedProperties[$modelClass][$propertyName] = $referencedProperty;
		}

		return Arrays::flatten($sortedRelatedProperties, true);
	}


	/**
	 * @param class-string<E> $entityClass
	 * @param array $containedModels
	 * @return RelatedProperty[]
	 */
	public static function &getPropertiesOfSelfEntities(string $entityClass): array
	{
		if (isset(self::$cache[__METHOD__][$entityClass])) {
			return self::$cache[__METHOD__][$entityClass];
		}
		$result = array_merge(
			static::getPropertiesOfSelfReferencedEntities($entityClass),
			static::getPropertiesOfSelfRelatedEntities($entityClass)
		);

		self::$cache[__METHOD__][$entityClass] = $result;
		return self::$cache[__METHOD__][$entityClass];
	}


	/**
	 * @param class-string<E> $entityClass
	 * @return RelatedProperty[]
	 */
	public static function &getPropertiesOfReferencedEntities(string $entityClass): array
	{
		if (isset(self::$cache[__METHOD__][$entityClass])) {
			return self::$cache[__METHOD__][$entityClass];
		}

		if ( ! is_a($entityClass, CakeEntity::class, true)) {
			$class = CakeEntity::class;
			throw new \InvalidArgumentException("'$entityClass' is not subclass of '$class'.");
		}

		$result = [];
		foreach (Reflection::getReflectionPropertiesOfClass($entityClass) as $property) {
			if ($property->isStatic() || $property->isPrivate() || $property->isProtected()) {
				continue;
			}
			if ($type = $property->getType()) {
				$keyPropertyName = $referencedKeyPropertyName = null;
				/** @var class-string<E> $referencedEntityClass */
				$referencedEntityClass = $type->getName();
				if ( ! is_a($referencedEntityClass, CakeEntity::class, true)) {
					continue;
				}
				if ($annotation = Reflection::parseAnnotation($property, 'var')) {
					// Pokud je @var anotace, explodnem si co je za ní, první část nás nezajímá, to je definice typu, ten už máme z reflexe
					$parts = preg_split('/[\s\t]+/', trim($annotation), -1, PREG_SPLIT_NO_EMPTY);
					if (count($parts) === 2) {
						// Případná první část je property vázaná na vazební sloupec v naší tabulce
						[, $keyPropertyName] = $parts;
					} elseif (count($parts) > 2) {
						// Případná druhá část je property vázaná na vazební sloupec v cizí tabulce
						[, $keyPropertyName, $referencedKeyPropertyName] = $parts;
					}
				}
				if ( ! $keyPropertyName) {
					// Defaultní
					$keyPropertyName = $property->getName() . ucfirst($entityClass::getPrimaryPropertyName());
				}
				if ( ! $referencedKeyPropertyName) {
					// Defaultní
					$referencedKeyPropertyName = $referencedEntityClass::getPrimaryPropertyName();
				}

				if ( ! array_key_exists($keyPropertyName, static::getColumnProperties($entityClass))
					|| ! array_key_exists($referencedKeyPropertyName, static::getColumnProperties($referencedEntityClass))
				) {
					// todo throw - tady je špatně nastavená anotace, ale asi jen nekdy
					continue;
				}

				$referenceProperty = new RelatedProperty();
				$referenceProperty->property = $property;
				$referenceProperty->columnProperty = static::getColumnProperties($entityClass)[$keyPropertyName];
				$referenceProperty->relatedColumnProperty = static::getColumnProperties($referencedEntityClass)[$referencedKeyPropertyName];
				$result[$property->getName()] = $referenceProperty;

			}
		}

		self::$cache[__METHOD__][$entityClass] = $result;
		return self::$cache[__METHOD__][$entityClass];
	}


	/**
	 * @param class-string<E> $entityClass
	 * @return RelatedProperty[]
	 */
	public static function &getPropertiesOfSelfReferencedEntities(string $entityClass): array
	{
		if (isset(self::$cache[__METHOD__][$entityClass])) {
			return self::$cache[__METHOD__][$entityClass];
		}
		$result = [];
		foreach (static::getPropertiesOfReferencedEntities($entityClass) as $referenceProperty) {
			if ($referenceProperty->relatedColumnProperty->entityClass === $entityClass) {
				$result[$referenceProperty->property->getName()] = $referenceProperty;
			}
		}

		self::$cache[__METHOD__][$entityClass] = $result;
		return self::$cache[__METHOD__][$entityClass];
	}


	/**
	 * @param class-string<E> $entityClass
	 * @return RelatedProperty[]
	 */
	public static function &getPropertiesOfOtherReferencedEntities(string $entityClass): array
	{
		if (isset(self::$cache[__METHOD__][$entityClass])) {
			return self::$cache[__METHOD__][$entityClass];
		}
		$result = [];
		foreach (static::getPropertiesOfReferencedEntities($entityClass) as $referenceProperty) {
			if ($referenceProperty->relatedColumnProperty->entityClass !== $entityClass) {
				$result[$referenceProperty->property->getName()] = $referenceProperty;
			}
		}

		self::$cache[__METHOD__][$entityClass] = $result;
		return self::$cache[__METHOD__][$entityClass];
	}


	/**
	 * @param class-string<E> $entityClass
	 * @return RelatedProperty[]
	 */
	public static function &getPropertiesOfRelatedEntities(string $entityClass): array
	{
		if (isset(self::$cache[__METHOD__][$entityClass])) {
			return self::$cache[__METHOD__][$entityClass];
		}


		if ( ! is_a($entityClass, CakeEntity::class, true)) {
			$class = CakeEntity::class;
			throw new \InvalidArgumentException("'$entityClass' is not subclass of '$class'.");
		}


		$result = [];
		foreach (Reflection::getReflectionPropertiesOfClass($entityClass) as $property) {
			if ($property->isStatic() || $property->isPrivate() || $property->isProtected()) {
				continue;
			}
			if ( ! $type = $property->getType()) {
				continue;
			}
			if ($type->getName() !== 'array') {
				continue;
			}

			if ($annotation = Reflection::parseAnnotation($property, 'var')) {
				$keyPropertyName = $referencedKeyPropertyName = null;
				// Pokud je @var anotace, explodnem si co je za ní, první část je definice typu,
				// druhá vazební property v cizí tabulce,
				// případná třetí vazební property v naší tabulce,
				$parsed = preg_split('/[\s\t]+/', trim($annotation), -1, PREG_SPLIT_NO_EMPTY);
				if (count($parsed) < 2) {
					continue;
				} elseif(count($parsed) === 2) {
					[$annotationType, $referencedKeyPropertyName] = $parsed;
				} elseif (count($parsed) > 2) {
					[$annotationType, $referencedKeyPropertyName, $keyPropertyName] = $parsed;
				}
				if ( ! $keyPropertyName) {
					$keyPropertyName = $entityClass::getPrimaryPropertyName();
				}
				// todo @deprecated
				if (strpos($referencedKeyPropertyName, '_') !== false) {
					$referencedKeyPropertyName = Strings::fromSnakeCaseToCamelCase($referencedKeyPropertyName);
				}// todo @deprecated

				$simpleType = preg_replace('/^(\w+).*$/', '$1', $annotationType);
				if ( ! $simpleType) {
					continue;
				}
				$relatedEntityClass = Reflection::expandClassName($simpleType, Reflection::getReflectionClass($entityClass));
				if ( ! is_a($relatedEntityClass, CakeEntity::class, true)) {
					continue;
				}

				if ( ! array_key_exists($keyPropertyName, static::getColumnProperties($entityClass))
					|| ! array_key_exists($referencedKeyPropertyName, static::getColumnProperties($relatedEntityClass))
				) {
					// todo throw - tady je špatně nastavená anotace ?
					continue;
				}

				$relatedProperty = new RelatedProperty();
				$relatedProperty->property = $property;
				$relatedProperty->columnProperty = static::getColumnProperties($entityClass)[$keyPropertyName];
				$relatedProperty->relatedColumnProperty = static::getColumnProperties($relatedEntityClass)[$referencedKeyPropertyName];
				$result[$property->getName()] = $relatedProperty;
			}
		}

		self::$cache[__METHOD__][$entityClass] = $result;
		return self::$cache[__METHOD__][$entityClass];
	}


	/**
	 * @param class-string<E> $entityClass
	 * @return RelatedProperty[]
	 */
	public static function &getPropertiesOfSelfRelatedEntities(string $entityClass): array
	{
		if (isset(self::$cache[__METHOD__][$entityClass])) {
			return self::$cache[__METHOD__][$entityClass];
		}
		$result = [];
		foreach (static::getPropertiesOfRelatedEntities($entityClass) as $referenceProperty) {
			if ($referenceProperty->relatedColumnProperty->entityClass === $entityClass) {
				$result[$referenceProperty->property->getName()] = $referenceProperty;
			}
		}

		self::$cache[__METHOD__][$entityClass] = $result;
		return self::$cache[__METHOD__][$entityClass];
	}


	/**
	 * @param class-string<E> $entityClass
	 * @return RelatedProperty[]
	 */
	public static function &getPropertiesOfOtherRelatedEntities(string $entityClass): array
	{
		if (isset(self::$cache[__METHOD__][$entityClass])) {
			return self::$cache[__METHOD__][$entityClass];
		}
		$result = [];
		foreach (static::getPropertiesOfRelatedEntities($entityClass) as $referenceProperty) {
			if ($referenceProperty->relatedColumnProperty->entityClass !== $entityClass) {
				$result[$referenceProperty->property->getName()] = $referenceProperty;
			}
		}

		self::$cache[__METHOD__][$entityClass] = $result;
		return self::$cache[__METHOD__][$entityClass];
	}

	/**
	 * @param CakeEntity $entity
	 * @param ColumnProperty|string $column
	 * @return ColumnProperty
	 */
	public static function getColumnProperty(CakeEntity $entity, $column): ColumnProperty
	{
		$entityClass = get_class($entity);
		if (is_string($column)) {
			$columnProperty = static::getColumnPropertiesByColumn($entityClass)[$column] ?? null;
			if ( ! $columnProperty) {
				throw new \InvalidArgumentException("Property '$column' does not exist in entity '$entityClass', or is not properly bind to the property.");
			}
		} elseif ($column instanceof ColumnProperty) {
			$columnProperty = $column;
		} else {
			$columnPropertyClass = ColumnProperty::class;
			throw new \InvalidArgumentException("Argument \$column must me either 'string' or '$columnPropertyClass' class.");
		}

		return $columnProperty;
	}




	/**
	 * @template T of \AppModel
	 * @param class-string<T> $modelClass
	 * @return T
	 * @throws \InvalidArgumentException Poud $modelClass není string se třídou potomka \AppModel
	 */
	private static function getModelStatic(string $modelClass)
	{
		if ( ! class_exists($modelClass, false) && ! \App::import('Model', $modelClass)) {
			// Soubor není ani načten, ani ho nelze najít mezi modely
			throw new \InvalidArgumentException("Model '$modelClass' does not exists.");
		}
		if ( ! is_a($modelClass, \AppModel::class, true)) {
			// Není to instanceof AppModel
			throw new \InvalidArgumentException("Model '$modelClass' is not instance of AppModel.");
		}
		if (\ClassRegistry::isKeySet($modelClass)) {
			// Model už je vytvořen v ClassRegistry
			return \ClassRegistry::getObject($modelClass);
		}

		// Model se vytvoří pomocí ClassRegistry
		return \ClassRegistry::init($modelClass);
	}
}