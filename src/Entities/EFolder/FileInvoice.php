<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FCurrency;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class FileInvoice extends CakeEntity
{
	public int $id;

	public int $processNumberId;

	public int $fCurrencyId;

	public int $fileId;

	public string $clientName;

	public ?float $deposit;

	public float $totalAmount;

	public ?float $commission;

	public DateTime $date;
	public ?DateTime $depositDueDate;
	public DateTime $dueDate;

	public ?DateTime $firstDay;

	public ?DateTime $lastDay;

	public FCurrency $fCurrency;

	public ProcessNumber $processNumber;

	public File $file;

	/**
	 * @var Invoice[] fileInvoiceId
	 */
	public array $invoices;


	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfFileInvoice';
	}

	public function getTotalAmountToPay(): float
	{
		return $this->fCurrency->round($this->totalAmount - ($this->commission ?? 0));
	}


	public function getSupplement(): float
	{
		return $this->fCurrency->round($this->getTotalAmountToPay() - ($this->deposit ?? 0));
	}


	public function hasInvoiceOfType(int $fInvoiceTypeId): bool
	{
		foreach ($this->invoices as $invoice) {
			if ($invoice->fInvoice->fInvoiceTypeId === $fInvoiceTypeId) {
				return true;
			}
		}
		return false;
	}


	public function getInvoicesOfType(int $fInvoiceTypeId): array
	{
		$invoices = [];
		foreach ($this->invoices as $invoice) {
			if ($invoice->fInvoice->fInvoiceTypeId === $fInvoiceTypeId) {
				$invoices[$invoice->id] = $invoice;
			}
		}

		return $invoices;
	}



}