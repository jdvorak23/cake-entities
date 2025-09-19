<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\Bases;

use Cesys\CakeEntities\Entities\UcaCustomer\Interfaces\IFCurrency;
use Cesys\CakeEntities\Model\Entities\CakeEntity;

abstract class BaseFCurrency extends CakeEntity implements IFCurrency
{
	public int $id;

	public ?string $name;

	public ?string $unit;

	/**
	 * 3-písmenný kód dle ISO-4217
	 * @var string|null
	 */
	public ?string $code;

	public int $roundCount;

	public ?bool $active;


	public function round(float $amount): float
	{
		return round($amount, $this->roundCount);
	}


	public function getRoundCount(): int
	{
		return $this->roundCount;
	}


	public function getCode(): string
	{
		return $this->code ?? '';
	}


	public function getId(): int
	{
		return $this->id;
	}
}