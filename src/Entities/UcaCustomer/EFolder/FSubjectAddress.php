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

	public ?string $houseNumber;

	public ?string $streetOther;

	public ?string $postcode;

	public ?string $city;

	public ?bool $active;

	public FCountry $fCountry;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfFSubjectAddress';
	}

	public function getZip(): string
	{
		$zip = preg_replace('/\s+/', '', $this->postcode);
		return trim($zip);
	}

	public function getFullStreet(): string
	{
		$street = (string) $this->street;
		if ( ! $this->houseNumber) {
			return $street;
		}
		return trim("$street $this->houseNumber");
	}
}