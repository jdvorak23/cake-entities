<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\SubEntities;

use Cesys\CakeEntities\Model\Entities\ArrayEntity;

class PriceCesys extends ArrayEntity
{
	/**
	 * Někdy v tom poli klíč 'room_name' chybí, takže nám sem dojde null
	 * @var string|null
	 */
	public ?string $roomName;

	public int $count;

	public bool $perDay;

	/**
	 * Název ceníkové položky
	 * @var string
	 */
	public string $name;

	public float $price;

	/**
	 * Tohle je k ničemu, tam kde se počítá celková cena localBookingu, se vůbec nekouká na tuto currency,
	 * takže i kdyby tam nekdy nějaká jiná (než local_bookings.total_price_currency_id) byla, tak to CeSYS nereflektuje.
	 * @var int|null
	 */
	//public ?int $currencyId;

	/**
	 * @param array $data
	 * @return static
	 */
	public static function createFromArray(array $data)
	{
		/*if (empty($data['currency_id'])) {
			// Bývá tam 0, to je pro nás null
			$data['currency_id'] = null;
		}*/

		return parent::createFromArray($data);
	}
}