<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class TemplateProperty extends CakeEntity
{
    public int $id;
    public string $name;

	public string $type;

    public bool $array;

    public bool $required;

    public ?DateTime $created;

    public ?DateTime $modified;

}