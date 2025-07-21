<?php

namespace Cesys\CakeEntities\Entities\AmadeusServer\EFolder;

use Cesys\CakeEntities\Entities\Glob\Currency;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class Contract extends CakeEntity
{
	public int $id;

	public ?int $reservationId;

	public ?string $tourOperatorName;

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
	 * Sice je nullable ale null nikdy není, to nemá smysl
	 * @var DateTime|null
	 */
	public ?DateTime $priceMaturity;

	/**
	 * Splatnost zálohy
	 * @var DateTime|null
	 */
	public ?DateTime $depositMaturity;

	/**
	 * Je sice nullable, jsou i záznamy s null, nicméně poslední v roce 2022
	 * Já počítám s tím, že tam vždy je a správně, jinak spadne úplně všechno...
	 * @var string|null 3písmenný kód, jako 'CZK', 'EUR', 'HUF', ...
	 */
	public ?string $paymentCurrency;


	/**
	 * @var Currency paymentCurrency code
	 */
	public Currency $currency;


	/**
	 * @var ContractService[] contract_id
	 */
	public array $contractServices;


	/**
	 * Musí se ručně doplnit, vytváří PriceHelper::getAmadeusPaymenSchedule()
	 * @var array
	 */
	public array $paymentSchedules;

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

	public function getPrice(): float
	{
		return $this->currency->round($this->paymentSchedules['totalPrice']['paymentCurrency']);
	}

	public function getCommission(): float
	{
		return $this->currency->round($this->paymentSchedules['commissions']['paymentCurrency']);
	}

	public function getPriceWithoutCommission(): float
	{
		return $this->getPrice() - $this->getCommission();
	}

	public function getDeposit(): float
	{
		return $this->currency->round($this->paymentSchedules['deposit']['paymentCurrency']);
	}

	public function getDepositWithoutCommission(): float
	{
		return $this->currency->round($this->paymentSchedules['priceWithoutCommissions']['deposit']['paymentCurrency']);
	}

	public function getSupplement(): float
	{
		return $this->currency->round($this->paymentSchedules['supplement']['paymentCurrency']);
	}

	public function getSupplementWithoutCommission(): float
	{
		return $this->currency->round($this->paymentSchedules['priceWithoutCommissions']['supplement']['paymentCurrency']);
	}

	public function getTotalPayment(): float
	{
		return $this->getDeposit() + $this->getSupplement();
	}

	public function getTotalPaymentWithoutCommission(): float
	{
		return $this->getDepositWithoutCommission() + $this->getSupplementWithoutCommission();
	}
}