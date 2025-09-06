<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Entities\UcaCustomer\Bases\BaseFCurrency;

class FCurrency extends BaseFCurrency
{
	public static function getModelClass(): string
	{
		return 'EfFCurrency';
	}
}