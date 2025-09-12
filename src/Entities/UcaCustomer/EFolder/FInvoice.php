<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Entities\EFolder\ExchangeRate;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class FInvoice extends CakeEntity
{
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

	public ?string $addressSubname;

	public ?string $addressStreet;

	public ?string $addressStreetOther;

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

	/**
	 * U Delta používáme pro uložení 'cesys_country' => z jakého cesysu přišla objednávka,
	 * což potřebují při exportu faktur pro určení tagu 'cinnost' a u vydaných pro 'stredisko'
	 * null, '' => tato informace u faktury není uvedena (staré)
	 * 'cz', 'sk', 'hu'
	 * @var string|null
	 */
	public ?string $deliveryNoteNumber;

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

	public ?float $base;

	public ?float $vat;

	public ?float $totalPayment;

	public ?float $round;

	public ?float $totalRound;

	public ?string $pdfView;

	public bool $send;

	public ?bool $active;

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

	public ?FSubject $fCustomer;

	public FSubject $fSupplier;

	public FSubjectBank $fSubjectBank;

	public FInvoiceType $fInvoiceType;

	
	/**
	 * @var callable
	 */
	protected $exchangeRateCallback;


	public static function getModelClass(): string
	{
		return 'EfFInvoice';
	}

	/**
	 * @param callable $exchangeRateCallback
	 * @return void
	 */
	public function setExchangeRateCallback(callable $exchangeRateCallback): void
	{
		$this->exchangeRateCallback = $exchangeRateCallback;
	}

	public function getExchangeRate(): ?ExchangeRate
	{
		$date = $this->issued ?? new DateTime('today');
		return ($this->exchangeRateCallback)($date, $this->fCurrencyId);
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


	/**
	 * "Znulovaná" faktura je taková faktura, která má všechny položky 0 (nejen totalRound)
	 * @return bool
	 */
	public function isZeroInvoice(): bool
	{
		foreach ($this->fInvoiceItems as $item) {
			if ( ! empty($item->total)) {
				return false;
			}
		}
		return true;
	}


	public function getItemOfTypeFeeOfInvoiceF(): ?FInvoiceItem
	{
		if ($this->fInvoiceTypeId !== FInvoiceType::F) {
			return null;
		}
		foreach ($this->fInvoiceItems as $fInvoiceItem) {
			if ($fInvoiceItem->fItemId === FInvoiceItem::TypeFeeOfFInvoice) {
				return $fInvoiceItem;
			}
		}
		return null;
	}
}