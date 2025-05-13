<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\Amadeus\EFolder\Reservation;
use Cesys\CakeEntities\Entities\CakeEntity;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FInvoice;

class Invoice extends CakeEntity
{
	public int $id;
	public int $fInvoiceId;

	public ?int $reservationId;

	public ?int $fileId;

	public FInvoice $fInvoice;

	public static function getModelClass(): string
	{
		return 'EfInvoice';
	}
}