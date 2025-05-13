<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\Amadeus\EFolder\Reservation;
use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class Folder extends CakeEntity
{
    public int $id;

	public string $number;
    public string $clientName;

    public ?DateTime $created;
    public ?DateTime $modified;

    public ?int $createdBy;
    public ?int $modifiedBy;

    /**
     * @var File[] folder_id
     */
    public array $files;

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
			$list[$processNumber->id] = $processNumber->number;
		}
		return $list;
	}

	/**
	 * @return Reservation[]
	 */
	public function getReservations(): array
	{
		$reservations = [];
		foreach ($this->processNumbers as $processNumber) {
			$reservations += $processNumber->reservations;
		}
		return $reservations;
	}
	public function getReservationsCount(): int
	{
		$count = 0;
		foreach ($this->processNumbers as $processNumber) {
			$count += count($processNumber->reservations);
		}
		return $count;
	}
}