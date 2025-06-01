<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FCurrency;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class ExchangeRate extends CakeEntity
{
	public int $id;

	public DateTime $date;

	/**
	 * 3-písmenný kód, toto je měna 'základní'
	 * @var string
	 */
	public string $refCurrency;

	/**
	 * 3-písmenný kód, toto je měna 'cizí'
	 * @var string
	 */
	public string $currency;
	public float $rate;

	public int $count;

	/**
	 * Ručně se musí dodat
	 * @var FCurrency
	 */
	public FCurrency $fRefCurrency;

	/**
	 * Ručně se musí dodat
	 * @var FCurrency
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