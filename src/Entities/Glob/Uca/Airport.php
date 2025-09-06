<?php

namespace Cesys\CakeEntities\Entities\Glob\Uca;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class Airport extends CakeEntity
{
	public int $id;

	public ?int $countryId;

	/**
	 * v db je nullable, ale nikdy není null
	 * @var string
	 */
	public ?string $city;

	public ?string $cityCs;

	public ?string $citySk;

	public ?string $cityHu;

	/**
	 * v db je nullable, ale nikdy není null
	 * @var string
	 */
	public string $name;

	public ?string $nameCs;

	public ?string $nameSk;

	public ?string $nameHu;

	public string $iataCode;

	public ?string $icaoCode;

	public ?float $latitude;

	public ? float $longitude;

	public ?int $altitude;

	/**
	 * sem tam se objeví null, bohužel
	 * @var string|null
	 */
	public ?string $timezone;

	/**
	 * v db je nullable, ale nikdy není null
	 * @var ?float
	 */
	public ?float $timezoneHour;

	public ?string $dst;

	protected string $lang;

	public function setLang(string $lang): void
	{
		$this->lang = $lang;
	}

	public function getName(): string
	{
		if (isset($this->lang) && isset($this->{'name' . ucfirst($this->lang)})) {
			return $this->{'name' . ucfirst($this->lang)};
		}
		return $this->name ?? '';
	}

	public function getCity(): string
	{
		if (isset($this->lang) && isset($this->{'city' . ucfirst($this->lang)})) {
			return $this->{'city' . ucfirst($this->lang)};
		}
		return $this->city ?? '';
	}
}