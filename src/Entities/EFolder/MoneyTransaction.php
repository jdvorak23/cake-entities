<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\EFolder\Interfaces\IMoneyTransaction;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FCurrency;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FInvoice;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FSubjectBank;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class MoneyTransaction extends CakeEntity implements IMoneyTransaction
{
	public int $id;
	
	public int $folderId;
	
	public ?int $fBankTransactionId;
	
	public ?int $fSubjectBankId;
	public ?int $reservationId;
	public ?int $fileInvoiceId;
	public ?int $bookingId;
	
	public DateTime $date;
	
	public string $name;
	
	public string $description;
	
	public ?string $variableSymbol;
	
	public ?float $amount;
	
	public int $fCurrencyId;

	/**
	 * @var string ENUM
	 */
	public string $method;

	/**
	 * @var string ENUM
	 */
	public string $type;
	
	public bool $isIncome;

	/**
	 * false - transakce proběhla
	 * true - transakce je očekávána (budoucí)
	 * @var bool
	 */
	public bool $isProspective;

	public bool $checked;

	public FCurrency $fCurrency;

	public ?FSubjectBank $fSubjectBank;

	/**
	 * @var callable
	 */
	protected $exchangeRateCallback;


	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfMoneyTransaction';
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
		return ($this->exchangeRateCallback)($this->date, $this->fCurrencyId);
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
			// Transakce, která je checked, nemůže být měněna
			return false;
		}
		if ($this->isProspective) {
			// Výhledová transakce nelze editovat, je automatická
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
		if ($this->isProspective) {
			// Výhledová transakce nelze chcecknout
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

		if ($this->isProspective) {
			// Výhledová transakce nelze smazat - je automatická a okamžitě by se zase vygenerovala
			return false;
		}

		return true;
	}
}