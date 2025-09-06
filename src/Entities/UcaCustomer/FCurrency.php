<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer;

use Cesys\CakeEntities\Entities\UcaCustomer\Bases\BaseFCurrency;

class FCurrency extends BaseFCurrency
{
	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EFCurrency';
	}
}