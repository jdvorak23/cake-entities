<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\Bases;

use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FCurrency;
use Cesys\CakeEntities\Entities\UcaCustomer\Interfaces\IBankTransaction;
use Cesys\CakeEntities\Model\Entities\CakeEntity;

abstract class BaseFBankTransaction extends CakeEntity implements IBankTransaction
{
	public int $id;
	public float $value;
	/**
	 * 3-písmenný kód (aka 'CZK)
	 * @var string
	 */
	public string $currency;
	public ?string $offsetName;
	public ?int $variableSymbol;
	public ?string $userIdentification;
	public ?string $userMessage;
	public array $moneyTransactions;

	public function getVariableSymbolsAmadeus(): array
	{
		$variableSymbol = $this->variableSymbol;
		if (strlen((string) $variableSymbol) !== 9) {
			$variableSymbol = null;
		}
		if ($variableSymbol) {
			return [$variableSymbol];
		}
		if ($this->userMessage) {
			// najdeme všechny přesně 9-ti místná čísla v userMessage
			preg_match_all('/(?<!\d)\d{9}(?!\d)/', $this->userMessage, $matches);
			if ( ! empty($matches[0])) {
				// Máme nějaké výsledky
				$numbers = array_unique($matches[0]); // v té zprávě může být ten samý VS vícekrát
				return $numbers;
			}
		}

		return [];
	}

	/**
	 * Pokud je u transakce variabilní symbol, vrací ten, pokud je párovatelný s rezervací (9 místný)
	 * Pokud není, prohledá zprávu, jestli v ní je právě jedno přesně 9-ti místné číslo, pokud ano, vrací ho
	 * Jinak vrací null
	 * @return int|null
	 */
	public function getUnambiguousVariableSymbolAmadeus(): ?int
	{
		$variableSymbol = $this->variableSymbol;
		if (strlen((string) $variableSymbol) !== 9) {
			$variableSymbol = null;
		}
		if ( ! $variableSymbol && $this->userMessage) {
			// najdeme všechny přesně 9-ti místná čísla v userMessage
			preg_match_all('/(?<!\d)\d{9}(?!\d)/', $this->userMessage, $matches);
			if ( ! empty($matches[0])) {
				// Máme nějaké výsledky
				$numbers = array_unique($matches[0]); // v té zprávě může být ten samý VS vícekrát
				if (count($numbers) === 1) {
					// Zajímá nás jen, když tam je jenom jeden VS, jinak to nejde jednoznačně přiřadit
					$variableSymbol = (int) $numbers[0];
				}
			}
		}

		return $variableSymbol;
	}


	/**
	 * Kdo poslal peníze
	 * @return string
	 */
	public function getName(): string
	{
		if (isset($this->offsetName)) {
			return $this->offsetName;
		}
		if (isset($this->userIdentification)) {
			return $this->userIdentification;
		}
		return '';
	}

	public function getAmount(): float
	{
		return $this->getFCurrency()->round($this->value);
	}

	public function getUsedAmount(): float
	{
		$used = 0;
		foreach ($this->moneyTransactions as $moneyTransaction) {
			$used = $this->getFCurrency()->round($used + $moneyTransaction->amount);
		}
		return $used;
	}

	public function getRemainingAmount(): float
	{
		return $this->getFCurrency()->round($this->getAmount() - $this->getUsedAmount());
	}


	protected abstract function getFCurrency(): BaseFCurrency;
}