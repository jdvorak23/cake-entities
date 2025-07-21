<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\EFolder\Interfaces\IBookingService;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class BookingService extends CakeEntity implements IBookingService
{
	public int $id;

	public int $bookingId;

	public string $type;

	public ?string $code;

	public ?string $name;

	public string $description;

	public float $amount;


	public ?DateTime $dateFrom;
	public ?DateTime $dateTo;


	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfBookingService';
	}

}