<?php

namespace Cesys\CakeEntities\Entities\Test;

use Cesys\CakeEntities\Entities\CakeEntity;

class Book extends CakeEntity
{
    public int $id;
    public ?int $parentId;

    public ?int $strejdaId;
    public int $authorId;
    public ?int $translatorId;
    public string $title;

    /**
     * @var Tag[] book_id
     */
    public array $tags;

    public Author $author;

    public ?Author $translator;

    public ?Book $parent;

    public ?Book $strejda;

    /**
     * @var Book[] parent_id
     */
    public array $children;

    public static function getModelClass(): string
    {
        return 'BookTest';
    }
}