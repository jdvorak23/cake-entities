<?php

namespace Cesys\CakeEntities\Entities;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class Relation
{
    public \ReflectionProperty $property;
    public string $column;
    /**
     * @var class-string<CakeEntity>
     */
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