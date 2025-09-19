<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class BookingDeposit extends CakeEntity
{
	public int $id;
	public int $bookingId;

	public float $amount;

	public DateTime $maturity;

	public static function getModelClass(): string
	{
		return 'EfBookingDeposit';
	}
}