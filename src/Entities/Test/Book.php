<?php

namespace Cesys\CakeEntities\Entities\Test;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class Book extends CakeEntity
{
    public int $id;
    public ?int $parentId;

    public ?int $strejdaId;
    public int $authorId;
    public ?int $translatorId;
    public string $title;

    public DateTime $created;

    public ?DateTime $retolda;

    /**
     * @var Tag[] book_id
     */
    public array $tags;

    public Author $author;

    public ?Author $translator;

    public ?Book $parent;

    /**
     * @var Book|null strejdaId
     */
    public ?Book $strejdomrd;

    /**
     * @var Book[] parent_id
     */
    public array $children;

    public static function getModelClass(): string
    {
        return 'BookTest';
    }
}