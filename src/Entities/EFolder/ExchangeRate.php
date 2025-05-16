<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class ExchangeRate extends CakeEntity
{
	public int $id;

	public DateTime $date;
	public string $refCurrency;

	public string $currency;
	public float $rate;

	public int $count;

	public function convertFrom(float $amountInForeignCurrency, ?int $decimals): float
	{
		$amount = $this->rate / $this->count * $amountInForeignCurrency;
		if ($decimals === null) {
			return $amount;
		}
		return round($amount, $decimals);
	}

	public function convertTo(float $amountInRefCurrency, ?int $decimals): float
	{
		$amount = $amountInRefCurrency * $this->count / $this->rate;
		if ($decimals === null) {
			return $amount;
		}
		return round($amount, $decimals);
	}
}