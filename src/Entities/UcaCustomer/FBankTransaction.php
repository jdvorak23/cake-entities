<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer;

use Cesys\CakeEntities\Entities\EFolder\MoneyTransaction;
use Cesys\CakeEntities\Entities\UcaCustomer\Bases\BaseFBankTransaction;
use Nette\Utils\DateTime;

class FBankTransaction extends BaseFBankTransaction
{
	public int $id;
	
	public int $fSubjectBankId;
	
	public int $transactionId;
	
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
	
	public ?string $bankName;
	
	public ?int $constantSymbol;
	
	public ?int $variableSymbol;
	
	public ?int $specificSymbol;
	
	public ?string $userIdentification;
	
	public ?string $userMessage;
	
	public ?string $operationType;
	
	public ?string $officerProceeded;
	
	public ?string $specification;
	
	public ?string $comment;
	
	public ?string $bic;
	
	public ?int $instructionId;
	
	public ?string $status;
	
	public bool $checked;

	/**
	 * null - nepoužitá
	 * true - použitá v plné výši
	 * false - odložena, ve smyslu nepoužita a nechceme použít
	 * @var bool|null
	 */
	public ?bool $used;

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
		return 'FBankTransaction';
	}

	protected function getFCurrency(): FCurrency
	{
		return $this->fCurrency;
	}
}