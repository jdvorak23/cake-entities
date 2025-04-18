<?php

namespace Cesys\CakeEntities\Entities;

class Relation
{
    public \ReflectionProperty $property;
    public string $column;
    public string $relatedEntityClass;

    public function __construct(
        \ReflectionProperty $property,
        string $column,
        string $relatedEntityClass
    )
    {
        $this->property = $property;
        $this->column = $column;
        $this->relatedEntityClass = $relatedEntityClass;
    }
}