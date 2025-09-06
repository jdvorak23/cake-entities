<?php

namespace Cesys\CakeEntities\Entities\AmadeusServer\EFolder;

use Cesys\CakeEntities\Entities\AmadeusServer\Interfaces\IContractService;
use Cesys\CakeEntities\Model\Entities\CakeEntity;

class ContractService extends CakeEntity implements IContractService
{
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

	/**
	 * Zde není kód dle ISO 4217, ale 'jednotka', tj. např. 'Kč', 'Eur', 'Ft'
	 * Je nullable, ale null nemá smysl a v db není
	 * @var ?string
	 */
	public ?string $currency;

	public ?float $exchangeRate;

	public ?float $originalPrice;

	/**
	 * Toto je klasický kód dle ISO 4217, tj. 'CZK', 'EUR', ...
	 * @var string|null
	 */
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
		return 'EfAmadeusContractService';
	}

}