<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class FSetting extends CakeEntity
{
	public int $id;

	public string $name;

	public ?string $value;

	public ?string $valueText;

	public ?int $datatype;

	public ?string $title;

	public ?string $description;

	public ?bool $protected;

	public ?bool $hidden;

	public ?string $defaultValue;

	public ?string $defaultValueText;

	public ?int $position;

	public ?bool $active;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfFSettings';
	}
}