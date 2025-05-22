<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Cesys\CakeEntities\Entities\EFolder\ExchangeRate;
use Nette\Utils\DateTime;

class FInvoice extends CakeEntity
{
	/**
	 * Odsud se to tahá skoro všude -> toto je hlavní definice toho, že hlavní měna Ekonomu Delta je 'CZK'
	 */
	const DefaultCurrencyCode = 'CZK';

	public int $id;

	public int $fCurrencyId;

	public int $fSubjectBankId;

	/**
	 * Ani nevím co to je, bere se z konstanty
	 * @var int
	 */
	public int $fSubjectPeopleId;

	/**
	 * Nemusí nutně existovat, bohužel není nullable, takže pokud není vazba, dává se 0
	 * @var int
	 */
	public int $fCustomerId;

	/**
	 * Vazba do statické tabulky, klientem needitovatelná
	 * 1 - Převodním příkazem
	 * 2 - Hotově
	 * 3 - Stržením z ceny
	 * 4 - Platba kartou
	 * @var int
	 */
	public int $fRemittanceId;

	public int $fSupplierId;

	/**
	 * Vazba do statické tabulky, řádky a idčka pevně dány, klientem editovatelné jen některé sloupce
	 * 1 - FAKTURA - DAŇOVÝ DOKLAD
	 * 2 - ZÁLOHOVÁ FAKTURA - NEDAŇOVÝ DOKLAD
	 * 3 - DOBROPIS - DAŇOVÝ DOKLAD
	 * A jen Delta:
	 * 4 - Faktura F
	 * 5 - Faktura U
	 * 6 - Faktura E
	 * @var int
	 */
	public int $fInvoiceTypeId;

	public int $csContractId;

	public string $guid;

	public ?string $number;

	public ?int $serialNumber;

	public ?string $year;

	/**
	 * Nevím, na co to je, Delta má všude 1 a to je i default, boolean 0,1
	 * Původně to asi mělo být rozlišení vydaná (1) / přijatá (0) faktura
	 * @var bool
	 */
	public bool $category;

	public ?string $addressName;

	public $addressSubname;

	public ?string $addressStreet;

	public $addressStreetOther;

	public ?string $addressPostcode;

	public ?string $addressCity;

	/**
	 * Zde je vždy název země (žádná id)
	 * @var string|null
	 */
	public ?string $addressCountry;

	/**
	 * U Delty je toto vazba na amadeus_server.reservation.number
	 * @var string|null
	 */
	public ?string $commissionNumber;

	public $deliveryNoteNumber;

	/**
	 * Historicky u Delty číslo složky
	 * @var string|null
	 */
	public ?string $orderNumber;

	public ?DateTime $issued;

	public ?DateTime $fulfilled;

	public ?DateTime $maturity;

	public ?string $variableSymbol;

	public ?string $specificSymbol;

	public ?string $constantSymbol;

	public $disbursed;

	public $disbursedDescription;

	public $disbursedNumber;

	public ?float $base;

	public ?float $vat;

	public ?float $totalPayment;

	public $roundCount;

	public ?float $round;

	public ?float $totalRound;

	public $currencyRate;

	public $referenceCurrencyRate;

	public ?string $pdfView;

	public $filename;

	public $hash;

	public $canceled;

	public bool $send;

	public $remind;

	public ?bool $active;

	public $locked;

	public $parentId;

	public bool $qrInvoice;

	/**
	 * ENUM 'base', 'total', 'column'
	 * vypocet dani, 'base' - ze zakladu, 'total' - z celkove částky, 'column' - po sloupcich
	 * default: base
	 * @var string
	 */
	public string $vatCalculation;

	public ?string $textItemTop;

	public ?string $textItemBottom;


	/**
	 * @var FInvoiceItem[] f_invoice_id
	 */
	public array $fInvoiceItems;

	public FCurrency $fCurrency;

	public FSubject $fCustomer;

	public FSubject $fSupplier;

	public FSubjectBank $fSubjectBank;

	public FInvoiceType $fInvoiceType;

	/**
	 * @var callable
	 */
	protected $exchangeRateCallback;


	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfFInvoice';
	}

	/**
	 * @param callable $exchangeRateCallback
	 * @return void
	 */
	public function setExchangeRateCallback(callable $exchangeRateCallback)
	{
		$this->exchangeRateCallback = $exchangeRateCallback;
	}

	public function getExchangeRate(): ?ExchangeRate
	{
		if ($this->fCurrency->code === static::DefaultCurrencyCode) {
			return null;
		}
		// TODO toto je pouze pro testování, aby vůbec šlo vytvářet, musí se smazat!
		$yesterday = new DateTime('today');
		$yesterday->modify('-1 day');
		if ( ! $this->issued || $this->issued > $yesterday) {
			$date = $yesterday;
		} else {
			$date = $this->issued;
		}
		// TODO vše mezi smazat!
		return ($this->exchangeRateCallback)($date, $this->fCurrency->code, static::DefaultCurrencyCode);
	}

	public function getTotalInDefaultCurrency(): float
	{
		if ($exchangeRate = $this->getExchangeRate()) {
			return $exchangeRate->convertFrom($this->totalRound);
		}
		return $this->totalRound;
	}

	public function getTotalDeposit()
	{
		$deposit = 0;
		foreach ($this->fInvoiceItems as $item) {
			if ($item->fItemId === FInvoiceItem::TypeDeposit) {
				$deposit += $item->total;
			}
		}
		return $deposit;
	}

	public function hasCommissionTypeItem(): bool
	{
		foreach ($this->fInvoiceItems as $item) {
			if ($item->fItemId === FInvoiceItem::TypeCommission) {
				return true;
			}
		}
		return false;
	}

	public function getZip(): string
	{
		$zip = preg_replace('/\s+/', '', $this->addressPostcode);
		return trim($zip);
	}

	public function getFullInvoiceNumber(): string
	{
		return "{$this->fInvoiceType->prefix}$this->number";
	}
}