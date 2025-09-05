<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\Server\EFolder\TourOperator;
use Cesys\CakeEntities\Model\Entities\CakeEntity;

class SupplierTourOperator extends CakeEntity
{
	public int $tourOperatorId;
	public int $supplierId;

	public TourOperator $tourOperator;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfSupplierTourOperator';
	}

	public static function getPrimaryPropertyName(): string
	{
		return 'tourOperatorId';
	}
}