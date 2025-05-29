<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FCurrency;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FInvoice;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FSubjectBank;
use Nette\Utils\DateTime;

class MoneyTransaction extends CakeEntity
{
	
	public int $id;
	
	public int $folderId;
	
	public ?int $fBankTransactionId;
	
	public ?int $fSubjectBankId;
	
	public DateTime $date;
	
	public string $name;
	
	public ?string $description;
	
	public ?string $variableSymbol;
	
	public float $amount;
	
	public int $fCurrencyId;
	
	public bool $isIncome;

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
		if ($this->fCurrency->code === FInvoice::DefaultCurrencyCode) {
			return null;
		}

		$date = min($this->date, new DateTime('yesterday'));

		return ($this->exchangeRateCallback)($date, $this->fCurrency->code, FInvoice::DefaultCurrencyCode);
	}

	/**
	 * Orientační
	 * @return float
	 */
	public function getAmountInDefaultCurrency(): float
	{
		if ($exchangeRate = $this->getExchangeRate()) {
			return $exchangeRate->convertFrom($this->amount);
		}
		return $this->amount;
	}
}