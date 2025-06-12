<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Entities\EFolder\ExchangeRate;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class FBankTransaction extends CakeEntity
{
	
	public int $id;
	
	public int $fSubjectBankId;
	
	public $transactionId;
	
	public DateTime $date;
	
	public float $value;

	/**
	 * 3-písmenný kód (aka 'CZK)
	 * @var string
	 */
	public string $currency;
	
	public $offset;
	
	public ?string $offsetName;
	
	public $bankCode;
	
	public $bankName;
	
	public $constantSymbol;
	
	public ?int $variableSymbol;
	
	public $specificSymbol;
	
	public ?string $userIdentification;
	
	public ?string $userMessage;
	
	public $operationType;
	
	public $officerProceeded;
	
	public $specification;
	
	public $comment;
	
	public $bic;
	
	public $instructionId;
	
	public $status;
	
	public $checked;

	public FSubjectBank $fSubjectBank;

	/**
	 * @var FCurrency currency code
	 */
	public FCurrency $fCurrency;


	/**
	 * @var callable
	 */
	protected $exchangeRateCallback;



	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfFBankTransaction';
	}

	public function getRoundedAmount(): float
	{
		return round($this->value, $this->fCurrency->roundCount);
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
		if ($this->currency === FInvoice::DefaultCurrencyCode) {
			return null;
		}

		$date = min($this->date, new DateTime('yesterday'));

		return ($this->exchangeRateCallback)($date, $this->fCurrency->id, FInvoice::DefaultCurrencyId);
	}

	/**
	 * Orientační
	 * @return float
	 */
	public function getAmountInDefaultCurrency(): float
	{
		if ($exchangeRate = $this->getExchangeRate()) {
			return $exchangeRate->convertFrom($this->value);
		}
		return $this->getRoundedAmount();
	}

	/**
	 * Kdo poslal peníze
	 * @return string
	 */
	public function getName(): string
	{
		if (isset($this->offsetName)) {
			return $this->offsetName;
		}
		if (isset($this->userIdentification)) {
			return $this->userIdentification;
		}
		return '';
	}
}