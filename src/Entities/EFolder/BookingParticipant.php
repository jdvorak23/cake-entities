<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\Server\EFolder\Country;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class BookingParticipant extends CakeEntity
{

	public int $id;
	public int $bookingId;
	
	public string $firstName;
	
	public string $lastName;
	
	public ?string $sex;
	
	public ?DateTime $birth;
	
	public ?int $countryId;

	public ?Country $country;

	public static function getModelClass(): string
	{
		return 'EfBookingParticipant';
	}

	public function getFullName(): string
	{
		return trim(trim($this->firstName) . ' ' . trim($this->lastName));
	}
}