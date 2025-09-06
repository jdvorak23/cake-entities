<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\SubEntities;

use Cesys\CakeEntities\Model\Entities\ArrayEntity;
use Nette\Utils\DateTime;

/**
 * Je určeno POUZE pro local bookingy, které mají source 'cesys_default'
 * Je určeno POUZE pro local bookingy po datu created >= 2023-01-01
 * V opačných případech je ta struktura ještě mnohem šílenější, tady máme jenom divný $departureId,
 * u Amadea či starších jsou další možnosti typů hodnot v properties, kde může být prakticky cokoli v čemkoli
 */
class OfferDetailCesys extends ArrayEntity
{
	public string $name;
	public string $originalName;
	public float $rating;
	public float $originalRating;
	public string $country;
	public int $countryId;
	public string $destination;
	public int $destinationId;
	public DateTime $dateFrom;
	public DateTime $dateTo;
	public int $duration;
	public ?int $durationNight;
	public ?int $boardingId;
	public ?int $transportId;
	public ?int $airportId;
	public ?int $tripTypeId;
	public float $priceAdult;
	public ?int $accommodationTypeId;
	public int $originalNumber;

	/**
	 * null - nema odjezdova mista,
	 * array -  s odjezdová místa autobusů, vlaků
	 * Pokud je array, klíče jsou server.departures idčka, ale to je pro každou národní úplně jinak
	 * Může být vyplněno, i když transport_id odkazuje na vlastní, nebo na leteckou dopravu, to se pak (asi) prostě ignoruje
	 * @var null|array
	 */
	public ?array $departureId;
	public string $tourOperator;
	public ?FlightInfoCesys $flightInfo;


	/**
	 * První dimenze s klíči 0...n rozděluje pokoje. To je zatím vždy jen jeden, ale až budeme umět víc pokojů, mlže být více
	 * druhá dimenze 0...n jsou jednotlivé služby, přičemž i cena za osobu/pokoj/... je taky služba, a pak jsou to
	 * šlužby, které nabízí CK, která organizuje zájezd (neplést se službami, které si daná CA může přidávat vlastní, ty jsou jinde)
	 * @var PriceCesys[][]
	 */
	public array $prices = [];

	/**
	 * @param array $data
	 * @return static
	 */
	public static function createFromArray(array $data)
	{
		$entity = parent::createFromArray($data);
		$entity->departureId = $data['departure_id'] ?? null;
		if ( ! isset($data['flight_info'])) {
			$entity->flightInfo = null;
		} else {
			$entity->flightInfo = new FlightInfoCesys($data['flight_info']);
		}
		if (isset($data['Prices'])) {
			foreach ($data['Prices'] as $roomPrices) {
				$pricesCesys = [];
				foreach ($roomPrices as $price) {
					$pricesCesys[] = PriceCesys::createFromArray($price);
				}
				$entity->prices[] = $pricesCesys;
			}
		}

		return $entity;
	}
}