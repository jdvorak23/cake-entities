<?php

namespace Cesys\CakeEntities\Entities\Amadeus\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Cesys\CakeEntities\Entities\EFolder\Invoice;
use Cesys\CakeEntities\Entities\EFolder\ProcessNumber;

class Reservation extends CakeEntity
{
	public int $id;

	public int $travelAgencyId;

	public ?int $customerId;

	public ?int $efProcessNumberId;

	public ?int $number;

	public ?string $processNumber;

	public string $reservationType;

	public ?int $commissionPercent;

	public string $firstname;

	public string $surname;


	/**
	 * @var Contract[] reservation_id
	 */
	public array $contracts;

	/**
	 * @var Invoice[] reservation_id
	 */
	public array $invoices;

	public static function getModelClass(): string
	{
		return 'EfAmadeusReservation';
	}

	public function getClientName(): string
	{
		return trim($this->firstname . ' ' . $this->surname);
	}

	public function getContract(): ?Contract
	{
		if ($this->contracts) {
			return current($this->contracts);
		}
		return null;
	}

	public function getInvoice(): ?Invoice
	{
		if ($this->invoices) {
			return current($this->invoices);
		}
		return null;
	}
}