<?php

namespace Cesys\CakeEntities\Entities\Server\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class Boarding extends CakeEntity
{
	public int $id;

	/**
	 * Nikdy není null
	 * @var string|null
	 */
	public ?string $name;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfBoarding';
	}
}