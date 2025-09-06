<?php

namespace Cesys\CakeEntities\Entities\Glob\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class Destination extends CakeEntity
{
	public int $id;
	public int $countryId;

	/**
	 * Nikdy není null
	 * @var string|null
	 */
	public ?string $name;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfDestination';
	}
}