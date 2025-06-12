<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FCurrency;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class ExchangeRate extends CakeEntity
{
	public int $id;

	public DateTime $date;

	public int $refCurrencyId;

	public int $currencyId;
	public float $rate;

	public int $count;

	/**
	 * @var FCurrency refCurrencyId
	 */
	public FCurrency $fRefCurrency;

	/**
	 * @var FCurrency currencyId
	 */
	public FCurrency $fCurrency;

	public function convertFrom(float $amountInForeignCurrency): float
	{
		$amount = $this->rate / $this->count * $amountInForeignCurrency;
		return round($amount, $this->fRefCurrency->roundCount);
	}

	public function convertTo(float $amountInRefCurrency): float
	{
		$amount = $amountInRefCurrency * $this->count / $this->rate;
		return round($amount, $this->fCurrency->roundCount);
	}
}