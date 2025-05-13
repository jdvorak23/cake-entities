<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\Amadeus\EFolder\Reservation;
use Cesys\CakeEntities\Entities\CakeEntity;

/**
 * process_number = vorgang, vorgangsnummer, buchungsnummer
 */
class ProcessNumber extends CakeEntity
{
	public int $id;

	public int $folderId;

	public string $number;

	/**
	 * @var Reservation[] ef_process_number_id
	 */
	public array $reservations;
}