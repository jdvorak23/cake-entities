<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class FCurrency extends CakeEntity
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

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfFCurrency';
	}

	public function round(float $amount): float
	{
		return round($amount, $this->roundCount);
	}
}