<?php

namespace Cesys\CakeEntities\Entities\AmadeusServer\EFolder;

use Cesys\CakeEntities\Entities\EFolder\Invoice;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class Reservation extends CakeEntity
{
	/**
	 * Typy enum $paymentCollection
	 */
	public const PaymentCollectionSeller = 'seller';
	public const PaymentCollectionTO = 'tour_operator';

	/**
	 * Typy 'enum' $reservationType. Ve skutečnosti není enum, ale pracuje se s ním tak
	 */
	public const ReservationTypeCustomDirectSell = 'customDirectSell';
	public const ReservationTypeCustomPartnerSell = 'customPartnerSell';
	public const ReservationTypeSystemDirectSell = 'systemDirectSell';
	public const ReservationTypeSystemPartnerSell = 'systemPartnerSell';

	/**
	 * Typy enum $paymentStatus
	 */
	public const PaymentStatusUnpaid = 'unpaid';

	public const PaymentStatusOther = 'other';

	public const PaymentStatusOption = 'option';

	public const PaymentStatusPaidDeposit = 'paid_deposit';

	public const PaymentStatusPaid = 'paid';

	public const PaymentStatusReturned = 'returned';

	public const PaymentStatusCancelled = 'cancelled';



	public int $id;

	public ?int $tourOperatorId;

	public int $travelAgencyId;

	public ?int $customerId;

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
	 * @var Contract[] reservation_id
	 */
	public array $contracts;

	/**
	 * @var Invoice[] reservation_id
	 */
	public array $invoices;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfAmadeusReservation';
	}

	public function getClientName(): string
	{
		return trim(trim($this->firstname) . ' ' . trim($this->surname));
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

	public function isPartnerSell(): bool
	{
		return $this->reservationType === self::ReservationTypeCustomPartnerSell
			|| $this->reservationType === self::ReservationTypeSystemPartnerSell;
	}

	public function getPrice(): float
	{
		return $this->getContract()->getPrice();
	}

	public function getCommission(): float
	{
		return $this->getContract()->getCommission();
	}

	public function getPriceWithoutCommission(): float
	{
		return $this->getContract()->getPriceWithoutCommission();
	}

	public function getDeposit(): float
	{
		return $this->paymentCollection === self::PaymentCollectionSeller
			? $this->getContract()->getDepositWithoutCommission()
			: $this->getContract()->getDeposit();
	}

	public function getSupplement(): float
	{
		return $this->paymentCollection === self::PaymentCollectionSeller
			? $this->getContract()->getSupplementWithoutCommission()
			: $this->getContract()->getSupplement();
	}

	public function getTotalPayment(): float
	{
		return $this->getDeposit() + $this->getSupplement();
	}
}