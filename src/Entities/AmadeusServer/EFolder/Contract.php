<?php

namespace Cesys\CakeEntities\Entities\AmadeusServer\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FSubject;
use Nette\Utils\DateTime;

class Contract extends CakeEntity
{
	public int $id;

	public ?int $reservationId;

	public ?string $travelAgencyName;

	public ?string $travelAgencyCompany;

	public ?string $travelAgencyInumber;

	public ?string $clientStreet;

	public ?string $clientHouseNumber;

	public ?string $clientCity;

	public ?string $clientZip;

	/**
	 * Bacha, v tomhle může být uloženo jak id country, tak název country
	 * @var string|null
	 */
	public ?string $clientCountry;

	public ?string $accommodation;

	/**
	 * Kam se jede, vždy název země
	 * @var string|null
	 */
	public ?string $country;

	public ?string $destination;

	public ?DateTime $dateFrom;

	public ?DateTime $dateTo;

	/**
	 * Splatnost
	 * @var DateTime|null
	 */
	public ?DateTime $priceMaturity;

	/**
	 * @var string|null 3písmenný kód, jako 'CZK', 'EUR', 'HUF', ...
	 */
	public ?string $paymentCurrency;

	public ?string $processNumber;

	/**
	 * @var ContractService[] contract_id
	 */
	public array $contractServices;

	/**
	 * Musí se ručně doplnit, vytváří PriceHelper::getAmadeusPaymenSchedule()
	 * @var array
	 */
	public array $paymentSchedules;

	/**
	 * Musí se ručně doplnit, páruje se mezi contracts.travel_agency_inumber a cz_c_2710.f_subjects.inumber
	 * @var ?FSubject
	 */
	public ?FSubject $partner;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfAmadeusContract';
	}

	public function getTravelAgencyInumber(): string
	{
		return trim(preg_replace('/\s/', '', $this->travelAgencyInumber));
	}

	public function getFullClientStreet(): string
	{
		return trim(trim((string) $this->clientStreet) . ' ' . trim((string) $this->clientHouseNumber));
	}

	public function isClientCountryId(): bool
	{
		if (empty($this->clientCountry)) {
			return false;
		}
		return ((string)(int) $this->clientCountry) === $this->clientCountry;
	}
}