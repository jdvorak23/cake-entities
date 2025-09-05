<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\Glob\EFolder\Destination;
use Cesys\CakeEntities\Entities\Glob\EFolder\Supermaster;
use Cesys\CakeEntities\Entities\Server\EFolder\Boarding;
use Cesys\CakeEntities\Entities\Server\EFolder\Country;
use Cesys\CakeEntities\Model\Entities\CakeEntity;

class BookingAccommodation extends CakeEntity
{
	public int $id;
	public int $countryId;
	public int $destinationId;
	public int $boardingId;

	public ?int $supermasterId;

	/**
	 * Název dle pořadatele
	 * @var string
	 */
	public string $name;

	public Country $country;

	public Destination $destination;

	public Boarding $boarding;

	public ?Supermaster $supermaster;


	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfBookingAccommodation';
	}
}