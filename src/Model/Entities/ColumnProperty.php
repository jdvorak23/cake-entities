<?php

namespace Cesys\CakeEntities\Model\Entities;

class ColumnProperty
{
	/**
	 * @var class-string<CakeEntity>
	 */
    public string $entityClass;

    public string $column;

    public string $propertyName;

    public \ReflectionProperty $property;

    public bool $isDateOnly = false;
}