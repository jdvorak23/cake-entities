<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\SubEntities;

use Cesys\CakeEntities\Model\Entities\ArrayEntity;
use Nette\Utils\DateTime;

/**
 * Je určeno POUZE pro local bookingy, které mají source 'cesys_default'
 */
class FlightCesys extends ArrayEntity
{
	public string $airportStart;
	public string $airportStartCode;
	public string $airportStartDate;

	public string $airportStartTime;

	public string $airportEnd;
	public string $airportEndCode;
	public string $airportEndDate;

	public string $airportEndTime;

	public string $companyCode;

	/**
	 * Je zde vždy to samé, jako v $companyCode (a je to code) -> toto platí jen pro cesys_default samozřejmě
	 * @var string
	 */
	public string $company;

	public string $flightNumber;
}