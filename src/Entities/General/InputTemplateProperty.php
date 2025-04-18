<?php

namespace Cesys\CakeEntities\Entities\General;

use Cesys\CakeEntities\Entities\CakeEntity;
use Cesys\CakeEntities\Entities\EFolder\TemplatePattern;
use Nette\Utils\DateTime;

class InputTemplateProperty extends CakeEntity
{
    public int $id;

    public int $inputTemplateId;

    public int $templatePatternId;

    /**
     * Pokud je v databázi určená, hledá se shoda
     * Pokud naopak není, hledá se patternem a bude (po úpravě) doplněna
     * @var string|null
     */
    public ?string $value;

    public ?DateTime $created;
    public ?DateTime $modified;

    public TemplatePattern $templatePattern;

    public function getName(): string
    {
        return $this->templatePattern->templateProperty->name;
    }

    public function convertAndSetValue(string $value): void
    {
        if ($this->templatePattern->getReplaceFrom()) {
            $value = str_replace($this->templatePattern->getReplaceFrom(), $this->templatePattern->getReplaceTo(), $value);
        }
        if (isset($this->templatePattern->dateFormat)) {
            $date = DateTime::createFromFormat($this->templatePattern->dateFormat, $value);
            if ($date) {
                if (strpos($this->templatePattern->dateFormat, '!') === 0) {
                    $this->value = $date->format('Y-m-d');
                } else {
                    $this->value = $date->format('Y-m-d H:i:s');
                }
            } else {
                $this->value = null;
            }
            return;
        }
        $this->value = $value;
    }
}