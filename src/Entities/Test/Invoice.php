<?php

namespace Cesys\CakeEntities\Entities\Test;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class Invoice extends CakeEntity
{
	public int $id;

	public int $databaseId;

	public int $fInvoiceId;
	public FInvoice $fInvoice;

	public static function getModelClass(): string
	{
		return 'InvoiceTest';
	}
}