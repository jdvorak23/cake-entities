<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class FolderDatabase extends CakeEntity
{
	public int $id;
	public string $customerDatabase;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfFolderDatabase';
	}
}