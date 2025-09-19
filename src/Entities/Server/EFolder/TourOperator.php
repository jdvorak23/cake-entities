<?php

namespace Cesys\CakeEntities\Entities\Server\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class TourOperator extends CakeEntity
{
	public int $id;
	public string $name;
	public string $internalCode;

	public static function getModelClass(): string
	{
		return 'EfTourOperator';
	}
}