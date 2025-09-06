<?php

namespace Cesys\CakeEntities\Model\Entities;

use Cesys\Utils\Reflection;
use Cesys\Utils\Strings;

abstract class ArrayEntity
{
	/**
	 * @param array $data
	 * @return static
	 */
	public static function createFromArray(array $data)
	{
		$entity = new static();
		foreach (Reflection::getReflectionPropertiesOfClass(static::class) as $property) {
			if ($property->isStatic() || $property->isPrivate() || $property->isProtected()) {
				continue;
			}
			if ($type = $property->getType()) {
				if ( ! in_array($type->getName(), ['int', 'float', 'string', 'bool'],true) && ! is_a($type->getName(), \DateTime::class, true)) {
					continue;
				}
			}
			$key = $property->getName();
			if ( ! isset($data[$key])) {
				$key = Strings::fromCamelCaseToSnakeCase($property->getName());
			}
			$value = $data[$key] ?? null;

			if ($type  && is_string($value)) {
				$typeName = $type->getName();
				if (is_a($typeName, \DateTime::class, true)) {
					if ($value) {
						if (strpos($value, ' ') === false) {
							$property->setValue($entity, $typeName::createFromFormat('!Y-m-d', $value));
						} else {
							$property->setValue($entity, $typeName::createFromFormat('Y-m-d H:i:s', $value));
						}
						continue;
					}
					$value = null;
				} elseif ($value === 'null' && $typeName !== 'string') {
					$value = null;
				}
			}

			if ($type && $value === '' && $type->allowsNull()) {
				$property->setValue($entity, null);
			} else {
				$property->setValue($entity, $value);
			}
		}
		return $entity;
	}
}