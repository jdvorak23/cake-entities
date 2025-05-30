<?php

namespace Cesys\CakeEntities\Entities\AmadeusServer\EFolder;

use Cesys\CakeEntities\Entities\AmadeusServer\EFolder\Contract;
use Cesys\CakeEntities\Entities\CakeEntity;
use Cesys\CakeEntities\Entities\EFolder\Invoice;
use Cesys\CakeEntities\Entities\EFolder\ProcessNumber;
use Nette\Utils\DateTime;

class Reservation extends CakeEntity
{
	/**
	 * Typy enum $paymentCollection
	 */
	const PaymentCollectionSeller = 'seller';
	const PaymentCollectionTO = 'tour_operator';

	/**
	 * Typy 'enum' $reservationType. Ve skutečnosti není enum, ale pracuje se s ním tak
	 */
	const ReservationTypeCustomDirectSell = 'customDirectSell';
	const ReservationTypeCustomPartnerSell = 'customPartnerSell';
	const ReservationTypeSystemDirectSell = 'systemDirectSell';
	const ReservationTypeSystemPartnerSell = 'systemPartnerSell';

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
	 * Viz constanty
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
}