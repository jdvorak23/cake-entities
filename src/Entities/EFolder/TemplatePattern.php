<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class TemplatePattern extends CakeEntity
{
    public int $id;

	public string $description;

	public string $pattern;

	public ?string $replacePatterns;

	public ?string $replacements;

	public ?string $dateFormat;

    public string $name;

    public ?DateTime $created;

    public ?DateTime $modified;

	public function getReplacePatterns(): array
	{
		return $this->replacePatterns ? (unserialize($this->replacePatterns) ?: []) : [];
	}

	public function getReplacements(): array
	{
		return $this->replacements ? (unserialize($this->replacements) ?: []) : [];
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
		if ($this->getReplacePatterns()) {
			$value = \preg_replace($this->getReplacePatterns(), $this->getReplacements(), $value);
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

		return $value === null ? null : trim($value);
	}

}