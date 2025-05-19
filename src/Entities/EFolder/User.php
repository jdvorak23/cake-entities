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

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfUser';
	}
}