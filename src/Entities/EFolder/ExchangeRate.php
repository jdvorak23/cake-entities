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
	 * Základní měna, tj. ta, do jejíž centrální banky se díváme na kurz
	 * @var FCurrency refCurrencyId
	 */
	public FCurrency $fRefCurrency;

	/**
	 * Cizí měna
	 * @var FCurrency currencyId
	 */
	public FCurrency $fCurrency;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfExchangeRate';
	}

	/**
	 * Zkonvertuje částku z cizí měny
	 * @param float $amountInForeignCurrency
	 * @param int|null $roundCount bere se, pokud je uveden. V případě null se bere z FCurrency::roundCount
	 * @return float
	 */
	public function convertFrom(float $amountInForeignCurrency, ?int $roundCount = null): float
	{
		$amount = $this->rate / $this->count * $amountInForeignCurrency;
		return round($amount, $roundCount ?? $this->fRefCurrency->roundCount);
	}

	/**
	 * Zkonvertuje částku do cizí měny
	 * @param float $amountInRefCurrency
	 * @param int|null $roundCount bere se, pokud je uveden. V případě null se bere z FCurrency::roundCount
	 * @return float
	 */
	public function convertTo(float $amountInRefCurrency, ?int $roundCount = null): float
	{
		$amount = $amountInRefCurrency * $this->count / $this->rate;
		return round($amount, $roundCount ?? $this->fCurrency->roundCount);
	}
}