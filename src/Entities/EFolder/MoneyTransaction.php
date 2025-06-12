<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FCurrency;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FInvoice;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FSubjectBank;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class MoneyTransaction extends CakeEntity
{
	public const TypeTransfer = 'transfer';
	public const TypeCard = 'card';
	public const TypeCash = 'cash';
	public const TypeVoucher = 'voucher';

	public const Types = [
		self::TypeCash => self::TypeCash,
		self::TypeTransfer => self::TypeTransfer,
		self::TypeCard => self::TypeCard,
		self::TypeVoucher => self::TypeVoucher,
	];

	public const ProspectiveTypeDeposit = 'deposit';
	public const ProspectiveTypeSupplement = 'supplement';

	public const ProspectiveTypeCommission = 'commission';

	
	public int $id;
	
	public int $folderId;
	
	public ?int $fBankTransactionId;
	
	public ?int $fSubjectBankId;
	
	public DateTime $date;
	
	public string $name;
	
	public ?string $description;
	
	public ?string $variableSymbol;
	
	public ?float $amount;
	
	public int $fCurrencyId;

	public ?string $type;
	
	public bool $isIncome;

	/**
	 * false - transakce proběhla
	 * true - transakce je očekávána (budoucí)
	 * @var bool
	 */
	public bool $isProspective;

	public ?string $prospectiveType;

	public bool $checked;

	public bool $active;

	public FCurrency $fCurrency;

	public ?FSubjectBank $fSubjectBank;

	/**
	 * @var callable
	 */
	protected $exchangeRateCallback;

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
		if ($this->fCurrencyId === FInvoice::DefaultCurrencyId) {
			return null;
		}

		$date = min($this->date, new DateTime('yesterday'));

		return ($this->exchangeRateCallback)($date, $this->fCurrencyId, FInvoice::DefaultCurrencyId);
	}

	/**
	 * Orientační
	 * @return float
	 */
	public function getAmountInDefaultCurrency(): float
	{
		if ( ! $this->amount) {
			return 0;
		}
		if ($exchangeRate = $this->getExchangeRate()) {
			return $exchangeRate->convertFrom($this->amount);
		}
		return $this->amount;
	}

	public function isEditable(): bool
	{
		if ($this->checked) {
			// Transakce, která je checked nemůže být měněna
			return false;
		}
		if ($this->prospectiveType) {
			// Výhledová transakce nemá jít editovat
			return false;
		}
		if ($this->fBankTransactionId) {
			// Transakce vytvořená z FBankTransaction -> nelze editovat
			return false;
		}
		return true;
	}

	public function isCheckable(): bool
	{
		if ($this->checked) {
			// Transakce, která je checked nemůže být měněna
			return false;
		}
		if ($this->amount === null) {
			// nezadaná částka, nemůže být checked
			return false;
		}
		if (in_array($this->prospectiveType, [self::ProspectiveTypeDeposit, self::ProspectiveTypeSupplement])) {
			// Výhledová transakce deposit / supplement, nemá být checkable
			return false;
		}
		$today = new DateTime('today');
		if ($this->date > $today) {
			// Budoucí transakce nemůže být checked
			return false;
		}
		return true;
	}

	public function isDeletable(): bool
	{
		if ($this->checked) {
			// Transakce, která je checked nemůže být měněna
			return false;
		}
		if ($this->prospectiveType) {
			// Výhledová transakce s prospectiveType nelze smazat
			return false;
		}
		return true;
	}
}