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

	public ?string $authorName;
    public ?int $translatorId;
    public string $title;

    public DateTime $created;

	public DateTime $modified;

    public ?DateTime $retolda;

    /**
     * @var Tag[] book_id
     */
    public array $tags;

    public Author $author;

    /**
     * @var ?Author authorName name
     */
    public ?Author $translator;

    public ?Book $parent;

    /**
     * @var Book|null
     */
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