<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\SubEntities;

/**
 * Je určeno POUZE pro local bookingy, které mají source 'cesys_default'
 */
class ServiceCesys
{
	public string $name;
	public string $priceName;

	public float $price;

	/**
	 * Nenašel jsem jediný záznam, kde by nebylo null (nebo 0, což je to samé)
	 * @var int|null
	 */
	public ?int $currencyId;

	/**
	 * @param array $data
	 * @return static
	 */
	public static function createFromArray(array $data)
	{
		$entity = new static();
		$entity->name = $data['name'];
		$entity->priceName = $data['price']['name'];
		$entity->price = $data['price']['value'];
		if (empty($data['price']['currency_id'])) {
			$entity->currencyId = null;
		} else {
			$entity->currencyId = $data['price']['currency_id'];
		}
		return $entity;
	}
}