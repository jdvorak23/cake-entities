<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\Glob\Currency;
use Cesys\CakeEntities\Model\Entities\CakeEntity;

class BookingPrice extends CakeEntity
{
	public int $id;

	public int $bookingId;

	public ?int $bookingRoomId;
	public string $description;

	public float $amount;

	public int $count;
	public bool $perDay;

	public ?BookingRoom $bookingRoom;


	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfBookingPrice';
	}


	/**
	 * Celková cena ceníkové položky
	 * @param int $days
	 * @param Currency $currency
	 * @return float
	 */
	public function getFullAmount(int $days, Currency $currency): float
	{
		if ($this->perDay) {
			return $currency->round($this->amount * $this->count * $days);
		}
		return $currency->round($this->amount * $this->count);
	}

	/**
	 * Jednotková cena ceníkové položky
	 * @param int $days
	 * @param Currency $currency
	 * @return float
	 */
	public function getSingleAmount(int $days, Currency $currency): float
	{
		if ($this->perDay) {
			return $currency->round($this->amount * $days);
		}
		return $this->amount;
	}
}