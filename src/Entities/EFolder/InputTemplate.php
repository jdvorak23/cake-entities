<?php

namespace Cesys\CakeEntities\Entities\EFolder;

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

    public function getProperty(string $propertyName): ?InputTemplateProperty
    {
        foreach ($this->properties as $property) {
            if ($property->getName() === $propertyName) {
                return $property;
            }
        }
        return null;
    }
}