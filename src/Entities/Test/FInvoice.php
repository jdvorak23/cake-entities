<?php

namespace Cesys\CakeEntities\Entities\Test;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class FInvoice extends CakeEntity
{
	public int $id;

	/**
	 * @var FInvoiceItem[] fInvoiceId
	 */
	public array $fInvoiceItems;

	public static function getModelClass(): string
	{
		return 'FInvoiceTest';
	}
}