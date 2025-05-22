<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;

class FInvoiceType extends CakeEntity
{
	const Issued = 1;
	const F = 10;
	const U = 11;
	const E = 12;

	public int $id;
	public ?string $prefix;
	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfFInvoiceType';
	}
}