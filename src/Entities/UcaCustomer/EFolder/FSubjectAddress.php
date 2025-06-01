<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class FSubjectAddress extends CakeEntity
{
	public int $id;

	public int $fSubjectId;

	public int $fSubjectAddressTypeId;

	public int $fCountryId;

	public ?string $name;

	public ?string $subname;

	public ?string $street;

	public $houseNumber;

	public $publicSpaceType;

	public $buildingNumber;

	public $floorNumber;

	public $entranceNumber;

	public $doorNumber;

	public ?string $streetOther;

	public ?string $postcode;

	public ?string $city;

	public $hash;

	public $active;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfFSubjectAddress';
	}

	public function getZip(): string
	{
		$zip = preg_replace('/\s+/', '', $this->postcode);
		return trim($zip);
	}
}