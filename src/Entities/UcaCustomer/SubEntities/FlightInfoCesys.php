<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\SubEntities;

use Cesys\CakeEntities\Model\Entities\ArrayEntity;


/**
 * Je určeno POUZE pro local bookingy, které mají source 'cesys_default'
 */
class FlightInfoCesys
{
	public array $departure = [];
	public array $arrival = [];

	public array $departure_total_flight_time;

	public array $arrival_total_flight_time;

	public function __construct(array $flightInfo)
	{
		foreach ($flightInfo['departure'] as $flight) {
			$this->departure[] = FlightCesys::createFromArray($flight);
		}
		foreach ($flightInfo['arrival'] as $flight) {
			$this->arrival[] = FlightCesys::createFromArray($flight);
		}
		$this->departure_total_flight_time = $flightInfo['departure_total_flight_time'];
		$this->arrival_total_flight_time = $flightInfo['arrival_total_flight_time'];
	}
}