<?php

namespace Cesys\CakeEntities\Entities\AmadeusServer\EFolder;

use Cesys\CakeEntities\Entities\AmadeusServer\Interfaces\IReservation;
use Cesys\CakeEntities\Entities\EFolder\File;
use Cesys\CakeEntities\Entities\EFolder\Invoice;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FInvoice;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class Reservation extends CakeEntity implements IReservation
{
	public int $id;

	public ?int $tourOperatorId;

	public int $travelAgencyId;

	public ?int $customerId;

	/**
	 * ENUM
	 * null => nějaká chyba, asi nějak špatně vytvořený řádek
	 * Určuje stát cestovní agentury (partnera), nebo jinak stát UCA, tj. jaká doména
	 * @var string|null
	 */
	public ?string $customerCountry;

	public ?int $efFolderId;

	public ?string $reservationStatus;

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

	/**
	 * Toto si určíme, pokud chceme, aby večer cron vytvořil vydanou fakturu k rezervaci. Pokud tam je starší datum ned dnešní,
	 * @var DateTime
	 */
	public ?DateTime $invoiceDate;

	public DateTime $created;

	/**
	 * @var Contract id reservationId
	 */
	public Contract $contract;

	/**
	 * @var Invoice[] reservationId
	 */
	public array $invoices;

	/**
	 * @var File[] reservationId
	 */
	public array $signedContracts;

	/**
	 * Pomůcka pro uložení předchozího stavu, není sloupec, ručně
	 * @var string
	 */
	public string $oldPaymentStatus;


	public static function getModelClass(): string
	{
		return 'EfAmadeusReservation';
	}


	public static function getExcludedFromProperties(): array
	{
		return ['oldPaymentStatus'];
	}


	public function getClientName(): string
	{
		return trim(trim($this->firstname) . ' ' . trim($this->surname));
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
		return $this->contract->currency->round($this->getDeposit() + $this->getSupplement());
	}

	/**
	 * @return FInvoice[]
	 */
	public function getFInvoices(): array
	{
		$fInvoices = [];
		foreach ($this->invoices as $invoice) {
			$fInvoices[$invoice->fInvoiceId] = $invoice->fInvoice;
		}
		return $fInvoices;
	}
}