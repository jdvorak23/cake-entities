<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\AmadeusServer\EFolder\Reservation;
use Cesys\CakeEntities\Entities\CakeEntity;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FInvoice;
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
		return static::$modelClasses[static::class] ??= 'EfFolder';
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

	/**
	 * @return Invoice[]
	 */
	public function getInvoices(): array
	{
		$invoices = [];
		foreach ($this->getReservations() as $reservation) {
			$invoices += $reservation->invoices;
		}
		foreach ($this->files as $file) {
			$invoices += $file->invoices;
		}
		return $invoices;
	}

	/**
	 * @return FInvoice[]
	 */
	public function getFInvoices(): array
	{
		$fInvoices = [];
		foreach ($this->getInvoices() as $invoice) {
			$fInvoices[$invoice->fInvoiceId] = $invoice->fInvoice;
		}
		return $fInvoices;
	}
}