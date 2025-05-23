<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\AmadeusServer\EFolder\ContractService;
use Cesys\CakeEntities\Entities\AmadeusServer\EFolder\Reservation;
use Cesys\CakeEntities\Entities\CakeEntity;
use Cesys\CakeEntities\Entities\EFolder\Custom\TransactionExpense;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FBankTransaction;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FInvoice;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FInvoiceType;
use Cesys\Utils\Arrays;
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

	/**
	 * Doplňuje se ručně
	 * První klíč je číslo rezervace a obsahuje všechny fBankTransactions (s klíčem (druhým) id)
	 * @var FBankTransaction[][]
	 */
	public array $fBankTransactions;

	protected array $transactionExpenses;

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
	public function getServiceContractServices(): array
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


	/**
	 * @return FBankTransaction[]
	 */
	public function getFBankTransactions(): array
	{
		return Arrays::flatten($this->fBankTransactions, true);
	}

	public function getTransactionsIncomeCount(): int
	{
		return count(Arrays::flatten($this->fBankTransactions));
	}

	public function getTransactionsExpensesCount(): int
	{
		$count = 0;
		foreach ($this->files as $file) {
			if ( ! $file->getParsedInvoice()) {
				continue;
			}
			$count++;
			$fileProcessNumber = $file->getParsedInvoice()->processNumber;

			if ( ! $processNumber = $this->getProcessNumbersByNumber()[$fileProcessNumber] ?? null) {
				continue;
			}
			foreach ($processNumber->reservations as $reservation) {
				if ($reservation->isPartnerSell() && $reservation->paymentCollection === Reservation::PaymentCollectionTO) {
					$count++;
				}
			}
		}
		return $count;
	}

	/**
	 * @return float[]
	 */
	public function getTransactionsIncomeTotals(): array
	{
		$totals = [];
		foreach ($this->getFBankTransactions() as $fBankTransaction) {
			if ( ! isset($totals[$fBankTransaction->currency])) {
				$totals[$fBankTransaction->currency] = 0;
			}
			$totals[$fBankTransaction->currency] += $fBankTransaction->value;
		}
		return $totals;
	}

	public function getTransactionsIncomeTotal(): float
	{
		$total = 0;
		foreach ($this->getFBankTransactions() as $fBankTransaction) {
			$total += $fBankTransaction->getAmountInDefaultCurrency();
		}
		return $total;
	}

	/**
	 * @return TransactionExpense[]
	 */
	public function getTransactionExpenses(): array
	{
		if (isset($this->transactionExpenses)) {
			return $this->transactionExpenses;
		}
		$transactionExpenses = [];
		foreach ($this->files as $file) {
			if ( ! $file->getParsedInvoice()) {
				continue;
			}

			$transactionExpense = new TransactionExpense();
			$transactionExpense->date = $file->getParsedInvoice()->dueDate;
			$transactionExpense->name = $file->getFullFilename();
			$transactionExpense->currency = $file->getParsedInvoice()->currency;
			$transactionExpense->amount = $file->getParsedInvoice()->getTotalPayment();
			$transactionExpense->amountInDefaultCurrency = $file->getParsedInvoice()->getTotalPaymentInDefaultCurrency();
			$transactionExpense->fileId = $file->id;
			$transactionExpenses[] = $transactionExpense;

			$fileProcessNumber = $file->getParsedInvoice()->processNumber;
			if ( ! $processNumber = $this->getProcessNumbersByNumber()[$fileProcessNumber] ?? null) {
				continue;
			}
			// Zde vratka provize při placení celé částky (i s provizí) na účet DELTA (SVK)
			foreach ($processNumber->reservations as $reservation) {
				if ($reservation->isPartnerSell() && $reservation->paymentCollection === Reservation::PaymentCollectionTO) {
					$transactionExpense = new TransactionExpense();
					$transactionExpense->date = $reservation->getContract()->dateTo;
					$transactionExpense->name = $reservation->number;
					$transactionExpense->currency = $reservation->getContract()->paymentCurrency;
					$transactionExpense->amount = $reservation->getContract()->paymentSchedules['commissions']['paymentCurrency'];
					$transactionExpense->reservationId = $reservation->id;
					if ($transactionExpense->currency !== FInvoice::DefaultCurrencyCode) {
						$date = min($transactionExpense->date, new DateTime('yesterday'));
						$exchangeRate = ($this->exchangeRateCallback)($date, $transactionExpense->currency, FInvoice::DefaultCurrencyCode);
						$transactionExpense->amountInDefaultCurrency = $exchangeRate->convertFrom($transactionExpense->amount);
					} else {
						$transactionExpense->amountInDefaultCurrency = $transactionExpense->amount;
					}
					$transactionExpenses[] = $transactionExpense;
				}
			}
		}

		$today = new DateTime('today');
		foreach ($this->getReservations() as $reservation) {
			foreach ($reservation->getContract()->contractServices as $contractService) {
				if ($contractService->kind === ContractService::KindServicePrice && $contractService->price) {
					$transactionExpense = new TransactionExpense();
					$transactionExpense->date = $reservation->created;
					$transactionExpense->name = $contractService->name;
					if ($contractService->originalCurrency) {
						$transactionExpense->currency  = $contractService->originalCurrency;
						$transactionExpense->amount = $contractService->originalPrice;
					} else {
						switch ($contractService->currency) { // todo sračka
							case 'Kč':
								$transactionExpense->currency = 'CZK';
								break;
							case 'Eur':
								$transactionExpense->currency = 'EUR';
								break;
							case 'Ft':
								$transactionExpense->currency = 'HUF';
								break;
						}
						$transactionExpense->amount = $contractService->price;
					}


					if ($transactionExpense->currency !== FInvoice::DefaultCurrencyCode) {
						$date = min($transactionExpense->date, new DateTime('yesterday'));
						$exchangeRate = ($this->exchangeRateCallback)($date, $transactionExpense->currency, FInvoice::DefaultCurrencyCode);
						$transactionExpense->amountInDefaultCurrency = $exchangeRate->convertFrom($transactionExpense->amount);
					} else {
						$transactionExpense->amountInDefaultCurrency = $transactionExpense->amount;
					}

					$transactionExpense->reservationId = $reservation->id;
					$transactionExpenses[] = $transactionExpense;
				}
			}
		}

		return $this->transactionExpenses = $transactionExpenses;
	}


	/**
	 * @return float[]
	 */
	public function getTransactionsExpensesTotals(): array
	{
		$totals = [];
		foreach ($this->getTransactionExpenses() as $transactionExpense) {
			if ( ! isset($totals[$transactionExpense->currency])) {
				$totals[$transactionExpense->currency] = 0;
			}
			$totals[$transactionExpense->currency] += $transactionExpense->amount;

		}
		return $totals;
	}

	public function getTransactionsExpensesTotal(): float
	{
		$total = 0;
		foreach ($this->getTransactionExpenses() as $transactionExpense) {
			$total += $transactionExpense->amountInDefaultCurrency;
		}
		return $total;
	}
}