<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\SubEntities;

use Cesys\CakeEntities\Model\Entities\ArrayEntity;
use Nette\Utils\DateTime;

/**
 * Je určeno POUZE pro local bookingy, které mají source 'cesys_default'
 */
class ParticipantCesys extends ArrayEntity
{
	public string $firstname;
	public string $lastname;

	/**
	 * 'm', 'f' nebo null když nevíme
	 * @var string|null
	 */
	public ?string $sex;

	/**
	 * Toto je country_id
	 * @var ?int
	 */
	public ?int $nationality;

	public ?DateTime $birth;

	public static function createFromArray(array $data)
	{
		if (isset($data['title'])) {
			if ($data['title'] === '0') {
				$data['sex'] = 'm';
			} elseif ($data['title'] === '1') {
				$data['sex'] = 'f';
			} else {
				$data['sex'] = null;
			}
		} else {
			$data['sex'] = null;
		}
		$data['birth'] = $data['birth'] ? DateTime::createFromFormat('j.n.Y', $data['birth']) : null;
		$entity = parent::createFromArray($data);
		return $entity;
	}
}