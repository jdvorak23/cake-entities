<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;

class FInvoiceType extends CakeEntity
{
	public int $id;
	public ?string $prefix;
	public static function getModelClass(): string
	{
		return 'EfFInvoiceType';
	}
}