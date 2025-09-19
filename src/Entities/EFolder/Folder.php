<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\AmadeusServer\EFolder\ContractService;
use Cesys\CakeEntities\Entities\AmadeusServer\EFolder\Reservation;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FBankTransaction;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FInvoice;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FInvoiceType;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class Folder extends CakeEntity
{
    public int $id;

	public int $folderDatabaseId;

	public string $number;
    public string $clientName;

	public ?DateTime $dateFrom;
	public ?DateTime $dateTo;

	public ?int $sellerId;
	public ?int $accountantId;


    public ?DateTime $created;
    public ?DateTime $modified;

    public ?int $createdBy;
    public ?int $modifiedBy;

	public ?User $seller;
	public ?User $accountant;

    /**
     * @var File[] folder_id
     */
    public array $files;

	/**
	 * @var ProcessNumber[] folderId
	 */
	public array $processNumbers;

	/**
	 * @var MoneyTransaction[] folderId
	 */
	public array $moneyTransactions;

	/**
	 * @var Reservation[] efFolderId
	 */
	public array $reservations;

	/**
	 * @var Booking[] folderId
	 */
	public array $bookings;


	public static function getModelClass(): string
	{
		return 'EfFolder';
	}

	public function getYearFromNumber(): string
	{
		return '20' . substr($this->number, 0, 2);
	}

	public function getProcessNumbersList(): array
	{
		$list = [];
		foreach ($this->processNumbers as $processNumber) {
			$list[$processNumber->id] = "$processNumber->number ({$processNumber->supplier->brand})";
		}
		return $list;
	}

	/**
	 * @return ProcessNumber[]
	 */
	public function getProcessNumbersByNumber(): array
	{
		$processNumbers = [];
		foreach ($this->processNumbers as $processNumber) {
			$processNumbers[$processNumber->number] = $processNumber;
		}
		return $processNumbers;
	}


	/**
	 * @return Reservation[]
	 */
	public function getReservationsByNumber(): array
	{
		$reservations = [];
		foreach ($this->reservations as $reservation) {
			$reservations[$reservation->number] = $reservation;
		}
		return $reservations;
	}
	
	
	public function getReservationsCustomerIds(): array
	{
		$customerIds = [];
		foreach ($this->reservations as $reservation) {
			if ( ! $reservation->customerId) {
				continue;
			}
			$customerIds[] = $reservation->customerId;
		}
		return array_unique($customerIds);
	}


	/**
	 *
	 * @return array id => číslo rezervace, jméno, měna
	 */
	public function getReservationsList(): array
	{
		$list = [];
		foreach ($this->reservations as $reservation) {
			$list[$reservation->id] = $reservation->number . ' - ' . $reservation->getClientName() . ' - ' . $reservation->contract->paymentCurrency;
		}

		return $list;
	}


	/**
	 * @return FileInvoice[]
	 */
	public function getFileInvoices(): array
	{
		$fileInvoices = [];
		foreach ($this->processNumbers as $processNumber) {
			$processNumberFileInvoices = $processNumber->fileInvoices;
			uasort($processNumberFileInvoices, fn(FileInvoice $a, FileInvoice $b) => $b->date <=> $a->date);
			$fileInvoices += $processNumberFileInvoices;
		}

		return $fileInvoices;
	}


	/**
	 * @return Invoice[]
	 */
	public function getInvoices(): array
	{
		$invoices = [];
		foreach ($this->reservations as $reservation) {
			foreach ($reservation->invoices as $invoice) {
				$invoices[$invoice->id] = $invoice;
			}
		}
		/* todo
		foreach ($this->bookings as $booking) {
			if ($booking->invoice) {
				$invoices[$booking->invoice->id] = $booking->invoice;
			}
		}
		*/
		foreach ($this->processNumbers as $processNumber) {
			foreach ($processNumber->fileInvoices as $fileInvoice) {
				$invoices += $fileInvoice->invoices;
			}
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


	public function getInvoicesTotalIncome(): float
	{
		$total = 0;
		foreach ($this->getFInvoices() as $fInvoice) {
			if ($fInvoice->fInvoiceTypeId === FInvoiceType::E || $fInvoice->fInvoiceTypeId === FInvoiceType::F) {
				continue;
			}
			$total += $fInvoice->getTotalInDefaultCurrency();
		}
		return $total;
	}

	public function getInvoicesTotalExpenses()
	{
		$total = 0;
		foreach ($this->getFInvoices() as $fInvoice) {
			if ($fInvoice->fInvoiceTypeId !== FInvoiceType::F) {
				continue;
			}
			$total += $fInvoice->getTotalInDefaultCurrency();
		}
		return $total;
	}

	public function getInvoicesTotal()
	{
		return $this->getInvoicesTotalIncome() - $this->getInvoicesTotalExpenses();
	}

//-------------------------------------------------------

	/**
	 * @return MoneyTransaction[]
	 */
	public function getTransactionsIncome(): array
	{
		$transactions = [];
		foreach ($this->moneyTransactions as $transaction) {
			if ($transaction->isIncome) {
				$transactions[$transaction->id] = $transaction;
			}
		}
		return $transactions;
	}

	/*public function getTransactionsIncomeCount(): int
	{
		$count = 0;
		foreach ($this->moneyTransactions as $transaction) {
			if ($transaction->isIncome) {
				$count++;
			}
		}
		return $count;
	}*/


	/**
	 * @return float[]
	 */
	public function getTransactionsIncomeTotals(): array
	{
		$totals = [];
		foreach ($this->moneyTransactions as $transaction) {
			if ( ! $transaction->isIncome) {
				continue;
			}
			if ( ! isset($totals[$transaction->fCurrencyId])) {
				$totals[$transaction->fCurrencyId] = 0;
			}
			$totals[$transaction->fCurrencyId] = $transaction->fCurrency->round($totals[$transaction->fCurrencyId] + (float) $transaction->amount);
		}
		return $totals;
	}

	public function getTransactionsIncomeTotal(): float
	{
		$total = 0;
		foreach ($this->moneyTransactions as $transaction) {
			if ( ! $transaction->isIncome) {
				continue;
			}
			$total += $transaction->getAmountInDefaultCurrency();
		}
		return $total;
	}

	public function getTransactionsIncomeActualTotal(): float
	{
		$total = 0;
		foreach ($this->moneyTransactions as $transaction) {
			if ( ! $transaction->isIncome || $transaction->isProspective) {
				continue;
			}
			$total += $transaction->getAmountInDefaultCurrency();
		}

		return $total;
	}



	/**
	 * @return MoneyTransaction[]
	 */
	public function getTransactionsExpenses(): array
	{
		$transactions = [];
		foreach ($this->moneyTransactions as $transaction) {
			if ( ! $transaction->isIncome) {
				$transactions[$transaction->id] = $transaction;
			}
		}
		return $transactions;
	}

	/*public function getTransactionsExpensesCount(): int
	{
		$count = 0;
		foreach ($this->moneyTransactions as $transaction) {
			if ( ! $transaction->isIncome) {
				$count++;
			}
		}
		return $count;
	}*/


	/**
	 * @return float[]
	 */
	public function getTransactionsExpensesTotals(): array
	{
		$totals = [];
		foreach ($this->moneyTransactions as $transaction) {
			if ($transaction->isIncome) {
				continue;
			}
			if ( ! isset($totals[$transaction->fCurrencyId])) {
				$totals[$transaction->fCurrencyId] = 0;
			}

			$totals[$transaction->fCurrencyId] = $transaction->fCurrency->round($totals[$transaction->fCurrencyId] + (float) $transaction->amount);
		}
		return $totals;
	}


	public function getTransactionsExpensesTotal(): float
	{
		$total = 0;
		foreach ($this->moneyTransactions as $transaction) {
			if ($transaction->isIncome) {
				continue;
			}
			$total += $transaction->getAmountInDefaultCurrency();
		}
		return $total;
	}


	public function getTransactionsExpensesActualTotal(): float
	{
		$total = 0;
		foreach ($this->moneyTransactions as $transaction) {
			if ($transaction->isIncome || $transaction->isProspective) {
				continue;
			}
			$total += $transaction->getAmountInDefaultCurrency();
		}
		return $total;
	}


	/**
	 * Todo rozšířit až booking
	 * @return string|null
	 */
	public function getCustomerCountry(): ?string
	{
		if ($this->reservations) {
			return current($this->reservations)->customerCountry;
		}
		return null;
	}
}