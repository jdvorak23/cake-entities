<?php

namespace Cesys\CakeEntities\Entities\General;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class InputTemplateProperty extends CakeEntity
{
    public int $id;

    public int $inputTemplateId;
    public string $name;

    public string $pattern;

    public string $value;
    public ?DateTime $created;
    public ?DateTime $modified;
}