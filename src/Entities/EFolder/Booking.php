<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\EFolder\Interfaces\IBooking;
use Cesys\CakeEntities\Entities\Glob\Currency;
use Cesys\CakeEntities\Entities\Glob\EFolder\Country;
use Cesys\CakeEntities\Entities\Glob\Uca\Airport;
use Cesys\CakeEntities\Entities\Server\Uca\Transport;
use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FSubject;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class Booking extends CakeEntity implements IBooking
{
	public int $id;

	public int $folderId;

	public int $fSupplierId;

	public ?int $fCustomerId;

	public ?int $bookingAccommodationId;

	public ?string $variableSymbol;

	public float $price;

	public int $currencyId;

	public ?float $commission;

	public bool $fullPayment;

	public ?DateTime $maturity;

	public DateTime $dateFrom;

	public DateTime $dateTo;

	public int $transportId;

	public ?int $airportId;

	public string $firstName;

	public string $lastName;

	public string $street;

	public string $city;

	public string $zip;

	public int $clientCountryId;

	public string $phone;

	public string $email;

	public ?string $clientNote;

	public FSubject $fSupplier;

	public ?FSubject $fCustomer;

	public Currency $currency;

	/**
	 * @var ?BookingAccommodation bookingAccommodationId
	 */
	public ?BookingAccommodation $accommodation;

	public Transport $transport;

	public ?Airport $airport;

	public Country $clientCountry;

	/**
	 * @var ?Invoice id bookingId
	 */
	public ?Invoice $invoice;

	/**
	 * @var BookingParticipant[] bookingId
	 */
	public array $participants;


	/**
	 * @var BookingPrice[] bookingId
	 */
	public array $prices;


	/**
	 * @var BookingService[] bookingId
	 */
	public array $services;


	/**
	 * @var BookingDeposit[] bookingId
	 */
	public array $deposits;


	public DateTime $created;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfBooking';
	}

	public function getClientName(): string
	{
		return trim(trim($this->firstName) . ' ' . trim($this->lastName));
	}

	public function getDurationDays(): int
	{
		return $this->dateTo->diff($this->dateFrom)->days + 1;
	}


	/**
	 * Spočítá cena, za kterou TO, který organizuje zájezd, prodává
	 * Tj. v tomto nejsou započítány služby přidávané prodejcem
	 * @return float
	 */
	public function getTotalPriceOfPrices(): float
	{
		$totalPrice = 0;
		$durationDays = $this->getDurationDays();
		foreach ($this->prices as $price) {
			$totalPrice += $this->currency->round($price->getFullAmount($durationDays, $this->currency));
		}

		return $this->currency->round($totalPrice);
	}


	public function getTotalPriceOfServices(): float
	{
		$totalPrice = 0;
		foreach ($this->services as $service) {
			$totalPrice += $this->currency->round($service->amount);
		}

		return $this->currency->round($totalPrice);
	}


	/**
	 * Kompletní koncová cena pro koncového klienta
	 * @return float
	 */
	public function getTotalPrice(): float
	{
		$totalPrice = $this->getTotalPriceOfPrices() + $this->getTotalPriceOfServices();
		return $this->currency->round($totalPrice);
	}


	public function getTotalPriceWithoutCommission(): float
	{
		return $this->currency->round($this->price - $this->commission ?? 0);
	}


	/**
	 * Vrací datum splatnosti (doplatku)
	 * @return DateTime
	 */
	public function getMaturity(): DateTime
	{
		if (isset($this->maturity)) {
			return $this->maturity;
		}
		// Entita nemusí mít created, tj. nová, ještě neuložená
		$diffDate = $this->created ?? new DateTime('today');
		$diffDays = $diffDate->diff($this->dateFrom)->days;
		if ($diffDays < static::DefaultMaturityDays) {
			return $diffDate;
		}

		return $this->dateFrom->modifyClone('- ' . static::DefaultMaturityDays . ' days');
	}


	/**
	 * Vypočítá doplatek, tj. poslední platbu
	 * @return float
	 */
	public function getFinalPaymentAmount() : float
	{
		$totalInDeposits = 0;
		foreach ($this->deposits as $deposit) {
			$totalInDeposits = $this->currency->round($totalInDeposits + $this->currency->round($deposit->amount));
		}

		return $this->currency->round($this->getTotalPaymentAmount() - $totalInDeposits);
	}


	/**
	 * Vypočítá částku, která má být zaplacena na účet Delty (cena rezervace - výše provize)
	 * @return float
	 */
	public function getTotalPaymentAmount(): float
	{
		return $this->fullPayment ? $this->price : $this->getTotalPriceWithoutCommission();
	}


	public function getVariableSymbol(): ?string
	{
		// todo process number first
		return $this->variableSymbol;
	}
}