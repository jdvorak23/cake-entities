<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class FBankTransaction extends CakeEntity
{
	const StatusMatched = 'matched';
	const StatusUnused = 'unused';

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

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'FBankTransaction';
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