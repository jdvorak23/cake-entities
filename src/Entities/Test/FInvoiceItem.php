<?php

namespace Cesys\CakeEntities\Entities\Test;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class FInvoiceItem extends CakeEntity
{
	public int $id;

	public int $fInvoiceId;

	public string $name;

	public static function getModelClass(): string
	{
		return 'FInvoiceItemTest';
	}
}