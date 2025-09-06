<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\Bases;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

abstract class BaseFCurrency extends CakeEntity
{
	public int $id;

	public ?string $name;

	public ?string $unit;

	/**
	 * 3-písmenný kód
	 * @var string|null
	 */
	public ?string $code;

	public int $roundCount;

	public ?bool $active;

	public function round(float $amount): float
	{
		return round($amount, $this->roundCount);
	}
}