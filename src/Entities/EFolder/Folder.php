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
	 * @var ProcessNumber[] folder_id
	 */
	public array $processNumbers;

	/**
	 * @var MoneyTransaction[] folder_id
	 */
	public array $moneyTransactions;

	/**
	 * Doplňuje se ručně podle created_by
	 * @var User|null
	 */
	public ?User $agent;

	/**
	 * Doplňuje se ručně
	 * První klíč je číslo rezervace a obsahuje všechny fBankTransactions (s klíčem (druhým) id)
	 * @var FBankTransaction[][]
	 */
	public array $fBankTransactions;

	/**
	 * @var callable
	 */
	protected $exchangeRateCallback;

	/**
	 * @param callable $exchangeRateCallback
	 * @return void
	 */
	public function setExchangeRateCallback(callable $exchangeRateCallback)
	{
		$this->exchangeRateCallback = $exchangeRateCallback;
	}


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
	public function getReservations(): array
	{
		$reservations = [];
		foreach ($this->processNumbers as $processNumber) {
			$reservations += $processNumber->reservations;
		}
		return $reservations;
	}

	/**
	 * @return Reservation[]
	 */
	public function getReservationsByNumber(): array
	{
		$reservations = [];
		foreach ($this->processNumbers as $processNumber) {
			foreach ($processNumber->reservations as $reservation) {
				$reservations[$reservation->number] = $reservation;
			}
		}
		return $reservations;
	}

	/**
	 *
	 * @return array id => number
	 */
	public function getReservationsList(): array
	{
		$reservations = [];
		foreach ($this->getReservations() as $reservation) {
			$reservations[$reservation->id] = $reservation->number;
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

	/**
	 * @return ContractService[]
	 */
	/*public function getServiceContractServices(): array
	{
		$contractServices = [];
		foreach ($this->getReservations() as $reservation) {
			foreach ($reservation->getContract()->contractServices as $contractService) {
				if ($contractService->kind === ContractService::KindServicePrice && $contractService->price) {
					$contractServices[$contractService->id] = $contractService;
				}
			}
		}
		return $contractServices;
	}*/


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
			if ($transaction->isIncome && $transaction->active) {
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
			if ( ! $transaction->isIncome || ! $transaction->active) {
				continue;
			}
			if ( ! isset($totals[$transaction->fCurrencyId])) {
				$totals[$transaction->fCurrencyId] = 0;
			}
			$totals[$transaction->fCurrencyId] += (float) $transaction->amount;
		}
		return $totals;
	}

	public function getTransactionsIncomeTotal(): float
	{
		$total = 0;
		foreach ($this->moneyTransactions as $transaction) {
			if ( ! $transaction->isIncome || ! $transaction->active) {
				continue;
			}
			$total += $transaction->getAmountInDefaultCurrency();
		}
		return $total;
	}

	public function getTransactionsIncomeActualTotal(): float
	{
		$total = 0;
		$today = new DateTime('today');
		foreach ($this->moneyTransactions as $transaction) {
			if ( ! $transaction->isIncome || ! $transaction->active || $transaction->date > $today) {
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
			if ( ! $transaction->isIncome && $transaction->active) {
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
			if ($transaction->isIncome || ! $transaction->active) {
				continue;
			}
			if ( ! isset($totals[$transaction->fCurrencyId])) {
				$totals[$transaction->fCurrencyId] = 0;
			}
			$totals[$transaction->fCurrencyId] += (float) $transaction->amount;

		}
		return $totals;
	}

	public function getTransactionsExpensesTotal(): float
	{
		$total = 0;
		foreach ($this->moneyTransactions as $transaction) {
			if ($transaction->isIncome || ! $transaction->active) {
				continue;
			}
			$total += $transaction->getAmountInDefaultCurrency();
		}
		return $total;
	}

	public function getTransactionsExpensesActualTotal(): float
	{
		$total = 0;
		$today = new DateTime('today');
		foreach ($this->moneyTransactions as $transaction) {
			if ($transaction->isIncome || ! $transaction->active  || $transaction->date > $today) {
				continue;
			}
			$total += $transaction->getAmountInDefaultCurrency();
		}
		return $total;
	}
}