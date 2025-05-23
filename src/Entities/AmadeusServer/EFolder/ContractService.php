<?php

namespace Cesys\CakeEntities\Entities\AmadeusServer\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class ContractService extends CakeEntity
{
	const KindPersonPrice = 1;
	const KindServicePrice = 3;

	public int $id;

	public $bookingServiceId;

	public int $contractId;

	/**
	 * Je sice nullable, ale null tam nikdy není
	 * Buď 1 - cena za osobu, nebo 3 - cena za službu. Viz konstanty
	 * @var int|null
	 */
	public ?int $kind;

	/**
	 * Je sice nullable, ale null tam nikdy není
	 * @var string|null
	 */
	public ?string $name;

	public $code;

	public $description;

	public $dateFrom;

	public $dateTo;

	public $pricePerUnit;

	/**
	 * Může být null, občas je řádek v contract_services použit jako upřesňující popisek (obvykle jiného řádku v contract_services)
	 * @var float|null
	 */
	public ?float $price;

	public $currency;

	public $exchangeRate;

	public ?float $originalPrice;

	public ?string $originalCurrency;

	public $discount;

	public $discountPercentage;

	public $required;

	public $count;

	public $clientAllocations;

	public $position;

	public $active;


	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfAmadeusContractService';
	}

}