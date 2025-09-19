<?php

namespace Cesys\CakeEntities\Entities\Glob;

use Cesys\CakeEntities\Entities\Glob\Interfaces\ICurrency;
use Cesys\CakeEntities\Model\Entities\CakeEntity;

class Currency extends CakeEntity implements ICurrency
{
	public int $id;

	/**
	 * 3-písmenný kód dle ISO-4217
	 * @var string
	 */
	public string $code;

	/**
	 * Symbol hlavní jednotky, původní
	 * @var string
	 */
	public string $unit;

	/**
	 * Hlavní jednotka v latince.
	 * Kvůli demenťárnám z minulosti, např. máš o currency jedinou informaci, a to je 'Eur' (ano, takové boží sloupce a párování tu máme)
	 * @var string
	 */
	public string $unitLatin;

	/**
	 * Na tolik desetinných míst v celém CeSYSu tuto měnu zaokrouhlujeme, když s ní počítáme
	 * Klienti můžou mít pro svoje f_věci definováno jinak ve svých f_currencies
	 * @var int
	 */
	public int $round;


	public function round(float $amount): float
	{
		return round($amount, $this->round);
	}

	public function getRoundCount(): int
	{
		return $this->round;
	}


	public function getCode(): string
	{
		return $this->code;
	}


	public function getId(): int
	{
		return $this->id;
	}
}