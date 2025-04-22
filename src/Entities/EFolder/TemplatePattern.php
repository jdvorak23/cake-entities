<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class TemplatePattern extends CakeEntity
{
    public int $id;

    public ?int $templatePropertyId;

    public ?int $parentId;

    public string $pattern;

    public ?string $replaceFrom;
    public ?string $replaceTo;

    public ?string $dateFormat;

    public ?DateTime $created;
    public ?DateTime $modified;

    public TemplateProperty $templateProperty;

    public TemplatePattern $parent;

    public function getReplaceFrom(): array
    {
        return $this->replaceFrom ? (unserialize($this->replaceFrom) ?: []) : [];
    }

    public function getReplaceTo(): array
    {
        return $this->replaceTo ? (unserialize($this->replaceTo) ?: []) : [];
    }

    public function convertAndSetValue(string $value): void
    {
        if ($this->getReplaceFrom()) {
            $value = str_replace($this->getReplaceFrom(), $this->getReplaceTo(), $value);
        }
        if (isset($this->dateFormat)) {
            $date = DateTime::createFromFormat($this->dateFormat, $value);
            if ($date) {
                if (strpos($this->dateFormat, '!') === 0) {
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