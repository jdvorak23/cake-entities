<?php

namespace Cesys\CakeEntities\Entities\AmadeusServer\EFolder;

use Cesys\CakeEntities\Entities\AmadeusServer\Interfaces\IReservation;
use Cesys\CakeEntities\Entities\EFolder\Invoice;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class Reservation extends CakeEntity implements IReservation
{
	public int $id;

	public ?int $tourOperatorId;

	public int $travelAgencyId;

	public ?int $customerId;

	public ?int $efFolderId;

	public ?int $efProcessNumberId;

	public ?string $paymentStatus;
	
	public string $reservationType;
	

	public ?int $number;

	public ?string $processNumber;

	public ?int $commissionPercent;

	public string $firstname;

	public string $surname;

	/**
	 * Viz public constanty
	 * @var string
	 */
	public string $paymentCollection;

	public DateTime $created;

	/**
	 * @var Contract id reservationId
	 */
	public Contract $contract;

	/**
	 * @var ?Invoice id reservationId
	 */
	public ?Invoice $invoice;

	/**
	 * Pomůcka pro uložení předchozího stavu, není sloupec, ručně
	 * @var string
	 */
	public string $oldPaymentStatus;


	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfAmadeusReservation';
	}


	public static function getExcludedFromProperties(): array
	{
		return ['oldPaymentStatus'];
	}


	public function getClientName(): string
	{
		return trim(trim($this->firstname) . ' ' . trim($this->surname));
	}


	/**
	 * @return Invoice|null
	 * @deprecated
	 */
	public function getInvoice(): ?Invoice
	{
		return $this->invoice;
	}


	public function isPartnerSell(): bool
	{
		return $this->reservationType === self::ReservationTypeCustomPartnerSell
			|| $this->reservationType === self::ReservationTypeSystemPartnerSell;
	}


	public function getPrice(): float
	{
		return $this->contract->getPrice();
	}


	public function getCommission(): float
	{
		return $this->contract->getCommission();
	}


	public function getPriceWithoutCommission(): float
	{
		return $this->contract->getPriceWithoutCommission();
	}


	public function getDeposit(): float
	{
		return $this->paymentCollection === self::PaymentCollectionSeller
			? $this->contract->getDepositWithoutCommission()
			: $this->contract->getDeposit();
	}


	public function getSupplement(): float
	{
		return $this->paymentCollection === self::PaymentCollectionSeller
			? $this->contract->getSupplementWithoutCommission()
			: $this->contract->getSupplement();
	}


	public function getTotalPayment(): float
	{
		return $this->getDeposit() + $this->getSupplement();
	}
}