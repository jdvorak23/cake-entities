<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class FItem extends CakeEntity
{
	public int $id;
	
	public ?string $name;

	public ?string $itemName;

	public static function getModelClass(): string
	{
		return 'EfFItem';
	}
}