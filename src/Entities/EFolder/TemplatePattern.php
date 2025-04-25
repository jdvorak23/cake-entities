<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class TemplatePattern extends CakeEntity
{
    public int $id;

	public ?int $parentId;

	public string $description;

	public string $pattern;

	public ?string $replaceFrom;

	public ?string $replaceTo;

	public ?string $dateFormat;

    public string $name;

    public ?DateTime $created;

    public ?DateTime $modified;

	public ?TemplatePattern $parent;

	public function getReplaceFrom(): array
	{
		return $this->replaceFrom ? (unserialize($this->replaceFrom) ?: []) : [];
	}

	public function getReplaceTo(): array
	{
		return $this->replaceTo ? (unserialize($this->replaceTo) ?: []) : [];
	}

	/**
	 * @param ?string $value
	 * @return ?string
	 */
	public function convertValue(?string $value): ?string
	{
		if ($value === null) {
			return null;
		}
		if ($this->getReplaceFrom()) {
			$value = str_replace($this->getReplaceFrom(), $this->getReplaceTo(), $value);
		}
		if (isset($this->dateFormat)) {
			$date = \DateTime::createFromFormat($this->dateFormat, $value);
			if ($date) {
				if (strpos($this->dateFormat, '!') === 0) {
					return $date->format('Y-m-d');
				} else {
					return $date->format('Y-m-d H:i:s');
				}
			}
			return null;
		}

		return trim($value);
	}

}