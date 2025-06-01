<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class TemplateProperty extends CakeEntity
{
	const TYPE_STRING = 'string';
	const TYPE_INT = 'int';
	const TYPE_FLOAT = 'float';
	const TYPE_DATE = 'date';

    public int $id;
    public string $name;

	public string $type;

    public bool $isArray;

	public bool $inArray;

    public bool $required;

    public ?DateTime $created;

    public ?DateTime $modified;

	public function convertValue(?string $value)
	{
		if ($value === null) {
			return null;
		}
		if ($value && ($this->isArray || $this->inArray)) {
			$value = unserialize($value);
		}
		return $this->convertValues($value);
	}

	private function convertValues($value)
	{
		if (is_array($value)) {
			return array_map(fn($item) => $this->convertValues($item), $value);
		}

		switch ($this->type) {
			case self::TYPE_INT:
				$value = (int) $value;
				break;
			case self::TYPE_FLOAT:
				$value = (float) $value;
				break;
			case self::TYPE_DATE:
				$value = \Cesys\Utils\DateTimeHelper::createValidFromFormat($value);
				break;
			default:
				break;
		}

		return $value;
	}
}