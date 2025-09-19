<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

/**
 * process_number = vorgang, vorgangsnummer, buchungsnummer
 */
class ProcessNumber extends CakeEntity
{
	public int $id;
	public int $folderDatabaseId;
	public int $folderId;

	public int $supplierId;

	public string $number;

	public bool $active;

	public Folder $folder;

	public Supplier $supplier;

	/**
	 * @var FileInvoice[] processNumberId
	 */
	public array $fileInvoices;


	public static function getModelClass(): string
	{
		return 'EfProcessNumber';
	}

	public function getLastFileInvoice(): ?FileInvoice
	{
		$foundFileInvoice = null;
		foreach ($this->fileInvoices as $fileInvoice) {
			if ( ! $fileInvoice->active) {
				continue;
			}
			if ( ! $foundFileInvoice) {
				$foundFileInvoice = $fileInvoice;
			} elseif ($fileInvoice->date > $foundFileInvoice->date) {
				$foundFileInvoice = $fileInvoice;
			} elseif ($fileInvoice->date == $foundFileInvoice->date && $fileInvoice->id > $foundFileInvoice->id) {
				$foundFileInvoice = $fileInvoice;
			}
		}
		return $foundFileInvoice;
	}


	/**
	 *
	 * @param int $fInvoiceTypeId
	 * @return Invoice[]
	 */
	public function getInvoicesOfType(int $fInvoiceTypeId): array
	{
		$invoices = [];
		foreach ($this->fileInvoices as $fileInvoice) {
			foreach ($fileInvoice->invoices as $invoice) {
				if ($invoice->fInvoice->fInvoiceTypeId === $fInvoiceTypeId) {
					$invoices[$invoice->id] = $invoice;
				}
			}
		}

		return $invoices;
	}


	/**
	 * @param int $fInvoiceId
	 * @return ?Invoice
	 */
	public function getInvoiceOfFInvoice(?int $fInvoiceId): ?Invoice
	{
		if ($fInvoiceId === null) {
			return null;
		}
		foreach ($this->fileInvoices as $fileInvoice) {
			foreach ($fileInvoice->invoices as $invoice) {
				if ($invoice->fInvoice->id === $fInvoiceId) {
					return $invoice;
				}
			}
		}
		return null;
	}


	public function isDeletable(): bool
	{
		if ( ! $this->active) {
			return false;
		}

		if ($this->fileInvoices) {
			return false;
		}

		return true;
	}
}