<?php

namespace Cesys\CakeEntities\Entities\General;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class AccommodationChain extends CakeEntity
{
    public int $id;
    public ?int $parentId;
    public ?string $name;
    public ?string $search;
    public ?string $note;
    public bool $active;
    public ?DateTime $created;
    public ?DateTime $modified;
}