<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class TemplatePattern extends CakeEntity
{
    public int $id;

    public ?int $templatePropertyId;

    public ?int $patternId;

    public string $name;

    public ?DateTime $created;

    public ?DateTime $modified;

    public TemplateProperty $templateProperty;

	public Pattern $pattern;

}