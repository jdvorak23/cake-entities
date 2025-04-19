<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class Folder extends CakeEntity
{
    public int $id;

    public string $name;

    public ?DateTime $created;
    public ?DateTime $modified;

    /**
     * @var File[] folder_id
     */
    public array $files;


	public static function getModelClass(): string
	{
		return 'EfFolder';
	}
}