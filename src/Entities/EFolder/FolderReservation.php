<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\Amadeus\EFolder\Reservation;
use Cesys\CakeEntities\Entities\CakeEntity;

class FolderReservation extends CakeEntity
{
	public int $id;

	public int $folderId;

	public int $reservationId;

	public ?int $processNumberId;

	public Reservation $reservation;

	public ?ProcessNumber $processNumber;

}