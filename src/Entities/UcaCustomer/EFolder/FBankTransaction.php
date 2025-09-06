<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Entities\EFolder\MoneyTransaction;
use Cesys\CakeEntities\Entities\UcaCustomer\Bases\BaseFBankTransaction;
use Nette\Utils\DateTime;

class FBankTransaction extends BaseFBankTransaction
{
	
	public int $id;
	
	public int $fSubjectBankId;
	
	//public $transactionId;
	
	public DateTime $date;
	
	public float $value;

	/**
	 * 3-písmenný kód (aka 'CZK)
	 * @var string
	 */
	public string $currency;
	
	public ?string $offset;
	
	public ?string $offsetName;
	
	public ?string $bankCode;
	
	//public $bankName;
	
	//public $constantSymbol;
	
	public ?int $variableSymbol;
	
	//public $specificSymbol;
	
	public ?string $userIdentification;
	
	public ?string $userMessage;
	
	//public $operationType;
	
	//public $officerProceeded;
	
	//public $specification;
	
	public ?string $comment;
	
	public ?string $bic;
	
	//public $instructionId;
	
	//public $status;
	
	//public $checked;

	/**
	 * null - nepoužitá
	 * true - použitá v plné výši
	 * false - odložena, ve smyslu nepoužita a nechceme použít
	 * @var bool|null
	 */
	public ?bool $used;

	public FSubjectBank $fSubjectBank;


	/**
	 * @var FCurrency currency code
	 */
	public FCurrency $fCurrency;


	/**
	 * @var MoneyTransaction[] fBankTransactionId
	 */
	public array $moneyTransactions;


	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfFBankTransaction';
	}


	protected function getFCurrency(): FCurrency
	{
		return $this->fCurrency;
	}
}