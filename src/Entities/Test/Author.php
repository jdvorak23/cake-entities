<?php

namespace Cesys\CakeEntities\Entities\Test;

use Cesys\CakeEntities\Entities\CakeEntity;

class Author extends CakeEntity
{
    public int $id;
    public string $name;

    /**
     * @var Book[] author_id
     */
    public array $books;

    /**
     * @var Tag[] author_id
     */
    public array $tags;

    public static function getModelClass(): string
    {
        return 'AuthorTest';
    }
}