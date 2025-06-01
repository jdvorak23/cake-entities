<?php

namespace Cesys\CakeEntities\Entities\Test;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class TagType extends CakeEntity
{
    public int $id;
    public string $label;

    public static function getModelClass(): string
    {
        return 'TagTypeTest';
    }
}