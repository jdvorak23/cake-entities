<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class InputTemplateProperty extends CakeEntity
{
    public int $id;

    public int $inputTemplateId;

    public int $templatePatternId;

	public ?int $parentId;

    /**
     * Pokud je v databázi určená, hledá se shoda
     * Pokud naopak není, hledá se patternem a bude (po úpravě) doplněna
     * @var string|null
     */
    public ?string $value;

    public ?DateTime $created;
    public ?DateTime $modified;

    public TemplatePattern $templatePattern;

	public ?InputTemplateProperty $parent;

    public function getName(): string
    {
        return $this->templatePattern->templateProperty->name;
    }

    public function convertAndSetValue(string $value): void
    {
        $this->value = $this->templatePattern->convertValue($value);
    }
}