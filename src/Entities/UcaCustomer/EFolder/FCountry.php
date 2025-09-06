<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class FCountry extends CakeEntity
{
	public int $id;

	public string $name;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfFCountry';
	}
}