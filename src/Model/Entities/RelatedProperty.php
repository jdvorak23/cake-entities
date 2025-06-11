<?php

namespace Cesys\CakeEntities\Model\Entities;

class RelatedProperty
{
	public \ReflectionProperty $property;

    public ColumnProperty $columnProperty;

    public ColumnProperty $relatedColumnProperty;
}