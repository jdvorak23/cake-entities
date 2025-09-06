<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\Server\EFolder\Country;
use Cesys\CakeEntities\Model\Entities\CakeEntity;

class Company extends CakeEntity
{
	
	public int $id;
	
	public string $vatNumber;

	public ?string $inumber;
	
	public string $name;
	
	public string $street;
	
	public string $city;
	
	public string $zip;
	
	public int $countryId;

	/**
	 * Jestli se mají vytvářet F faktury
	 * @var bool
	 */
	public bool $fInvoices;

	/**
	 * Jestli se mají vytvářet UE faktury.
	 * Pokud ano, musí být známá provize, tj. při čtení faktury musíme mít TemplateProperty 'commission'
	 * @var bool
	 */
	public bool $ueInvoices;

	public Country $country;

	public static function getModelClass(): string
	{
		return 'EfCompany';
	}
}