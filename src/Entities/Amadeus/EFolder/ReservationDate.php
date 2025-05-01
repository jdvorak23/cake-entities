<?php

namespace Cesys\CakeEntities\Entities\Amadeus\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;

class ReservationDate extends CakeEntity
{
	public int $id;

	public ?int $reservationId;
	public \DateTime $dateFrom;

	public static function getModelClass(): string
	{
		return 'EfAmadeusReservationDate';
	}

}