<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class BookingRoom extends CakeEntity
{
	public int $id;

	public string $name;


	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfBookingRoom';
	}
}