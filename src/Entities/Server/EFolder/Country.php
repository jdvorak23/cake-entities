<?php

namespace Cesys\CakeEntities\Entities\Server\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class Country extends CakeEntity
{
	public int $id;
	/**
	 * Nikdy není null
	 * @var string|null
	 */
	public ?string $name;

	public static function getModelClass(): string
	{
		return 'EfCountry';
	}
}