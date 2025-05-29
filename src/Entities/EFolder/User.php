<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;

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
		return static::$modelClasses[static::class] ??= 'EfUser';
	}

	public function getName(): string
	{
		if (isset($this->superuserId)) {
			return "$this->name (CeSYS)";
		}
		return $this->name;
	}
}