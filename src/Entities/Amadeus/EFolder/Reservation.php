<?php

namespace Cesys\CakeEntities\Entities\Amadeus\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;

class Reservation extends CakeEntity
{
	public int $id;
	public ?int $number;
	public string $firstname;

	public string $surname;

	/**
	 * @var ReservationDate[] reservation_id
	 */
	public array $reservationDates;

	public static function getModelClass(): string
	{
		return 'EfAmadeusReservation';
	}

	public function getClientName(): string
	{
		return trim($this->firstname . ' ' . $this->surname);
	}

	public function getDate(): ?ReservationDate
	{
		if ($this->reservationDates) {
			return current($this->reservationDates);
		}
		return null;
	}
}