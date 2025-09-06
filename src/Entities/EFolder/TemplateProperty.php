<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\EFolder\Interfaces\ITemplateProperty;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Cesys\Utils\DateTimeHelper;
use Nette\Utils\DateTime;

class TemplateProperty extends CakeEntity implements ITemplateProperty
{
    public int $id;

    public string $name;

	public string $type;

    public bool $isArray;

	public bool $inArray;

    public bool $required;

    public ?DateTime $created;

    public ?DateTime $modified;

	public static function getModelClass(): string
	{
		return 'EfTemplateProperty';
	}


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
			case static::TypeInt:
				$value = (int) $value;
				break;
			case static::TypeFloat:
				$value = (float) $value;
				break;
			case static::TypeDate:
				$value = DateTimeHelper::createValidFromFormat($value);
				break;
			default:
				break;
		}

		return $value;
	}
}