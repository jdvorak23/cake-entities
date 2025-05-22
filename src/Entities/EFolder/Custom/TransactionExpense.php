<?php

namespace Cesys\CakeEntities\Entities\EFolder\Custom;

use Cesys\CakeEntities\Entities\EFolder\ExchangeRate;
use Nette\Utils\DateTime;
class TransactionExpense
{
	public DateTime $date;

	public string $name;

	public string $currency;

	public float $amount;

	public float $amountInDefaultCurrency;

	public ?int $fileId;

	public ?int $reservationId;
}