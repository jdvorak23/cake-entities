<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FInvoice;
use Cesys\CakeEntities\Model\Entities\CakeEntity;

class Invoice extends CakeEntity
{
	public int $id;
	public int $fInvoiceId;

	public ?int $reservationId;

	public ?int $fileId;

	public FInvoice $fInvoice;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfInvoice';
	}
}