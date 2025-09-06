<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class Supplier extends CakeEntity
{
	public int $id;
	public int $companyId;
	public string $brand;

	public Company $company;

	/**
	 * @var SupplierTourOperator[] supplierId
	 */
	public array $tourOperators;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfSupplier';
	}
}