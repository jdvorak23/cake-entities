<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\AmadeusServer\EFolder\Reservation;
use Cesys\CakeEntities\Entities\CakeEntity;

/**
 * process_number = vorgang, vorgangsnummer, buchungsnummer
 */
class ProcessNumber extends CakeEntity
{
	public int $id;

	public int $folderId;

	public string $number;

	/**
	 * @var Reservation[] ef_process_number_id
	 */
	public array $reservations;

	public function getLastDateTo(): ?\DateTime
	{
		$lastDate = null;
		foreach ($this->reservations as $reservation) {
			if ( ! $contract = $reservation->getContract()) {
				continue;
			}
			if ( ! $contract->dateTo) {
				continue;
			}
			if ( ! $lastDate || $contract->dateTo > $lastDate) {
				$lastDate = $contract->dateTo;
			}
		}
		return $lastDate;
	}
}