<?php

namespace Cesys\CakeEntities\Entities\General;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class InputTemplatePropertyZaloha extends CakeEntity
{
    public int $id;

    public int $inputTemplateId;
    public string $name;

    public string $pattern;

    public ?string $value;

    public ?string $replaceFrom;
    public ?string $replaceTo;

    public ?string $dateFormat;

    public ?DateTime $created;
    public ?DateTime $modified;

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