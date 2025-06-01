<?php

namespace Cesys\CakeEntities\Entities\Test;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class Tag extends CakeEntity
{
    public int $id;
    public int $tagTypeId;
    public ?int $bookId;
    public ?int $authorId;

    public TagType $tagType;

    public static function getModelClass(): string
    {
        return 'TagTest';
    }
}