<?php

namespace Cesys\CakeEntities\Entities\General;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class InputTemplate extends CakeEntity
{
    public int $id;

    public string $company;

    public string $type;

    public string $invoiceType;
    public ?DateTime $created;
    public ?DateTime $modified;

    /**
     * @var InputTemplateProperty[] input_template_id
     */
    public array $properties;
}