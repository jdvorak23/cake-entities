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

	public function convertFrom(float $amountInForeignCurrency): float
	{
		return $this->rate * $this->count * $amountInForeignCurrency;
	}

	public function convertTo(float $amountInRefCurrency): float
	{
		return $amountInRefCurrency / ($this->rate * $this->count);
	}
}