<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class User extends CakeEntity
{
    public int $id;
    public int $customerId;
    public int $userId;
    public string $country;
    public ?int $superuserId;

	public string $name;

	public static function getModelClass(): string
	{
		return 'EfUser';
	}

	public function getName(): string
	{
		$country = strtolower($this->country);
		if (isset($this->superuserId)) {
			return "$this->name ($country - CeSYS)";
		}
		return "$this->name ($country - $this->customerId)";
	}
}