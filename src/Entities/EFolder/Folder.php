<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class Folder extends CakeEntity
{
    public int $id;

	public string $number;
    public string $clientName;

    public ?DateTime $created;
    public ?DateTime $modified;

    /**
     * @var File[] folder_id
     */
    public array $files;

	/**
	 * @var FolderReservation[] folder_id
	 */
	public array $folderReservations;

	/**
	 * @var ProcessNumber[] folder_id
	 */
	public array $processNumbers;


	public static function getModelClass(): string
	{
		return 'EfFolder';
	}

	public function getProcessNumbersList(): array
	{
		$list = [];
		foreach ($this->processNumbers as $processNumber) {
			$list[$processNumber->id] = $processNumber->processNumber;
		}
		return $list;
	}
}