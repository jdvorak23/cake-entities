<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Entities\UcaCustomer\SubEntities\OfferDetailCesys;
use Cesys\CakeEntities\Entities\UcaCustomer\SubEntities\ParticipantCesys;
use Cesys\CakeEntities\Entities\UcaCustomer\SubEntities\ServiceCesys;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

/**
 * Je určeno POUZE pro local bookingy, které mají source 'cesys_default'
 * Je určeno POUZE pro local bookingy po datu created >= 2023-01-01
 * V opačných případech je ta struktura serializovaných dat ještě mnohem šílenější, kde může být prakticky cokoli v čemkoli
 */
class LocalBooking extends CakeEntity
{
	public int $id;
	
	public int $tourOperatorId;

	public int $tripId;
	
	public string $source;

	/**
	 * Tato cena nezahrnuje externí služby, tj. ty, co si doplňuje prodejce (NIKOLI organizující TO, to je něco jiného)
	 * Akorát je blbě spočítána, pokud v `Prices` je záporná položka (sleva)
	 * Takže je to stejně k hovnu a musíš si to vypočítat...
	 * @var ?float
	 * @deprecated nepoužívat a vždy počítat přes static::getTotalPrice()
	 */
	public ?float $totalPrice;

	/**
	 * local_bookings.total_price_currency_id je sice nullable, ale nikde v žádné db není null ani 0
	 * @var int
	 */
	public int $totalPriceCurrencyId;
	
	public ?string $firstname;
	
	public ?string $name;
	
	public ?string $phone;
	
	public ?string $email;
	
	public ?string $street;
	
	public ?string $city;
	
	public ?string $postCode;

	/**
	 * Je country id, na starých / amadeus může být jinak, to nás nezajímá
	 * @var int
	 */
	public int $country;
	
	public ?string $participants;

	/**
	 * Toto je poznámka objednavatele zájezdu
	 * @var string
	 */
	public string $text;

	public ?bool $processed;
	
	public string $offerDetail;
	
	public ?string $services;

	public ?DateTime $created;
	public ?DateTime $modified;

	private array $participantObjects;
	private ?OfferDetailCesys $offerDetailObject;

	private array $serviceObjects;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfLocalBooking';
	}


	/**
	 * @param array $data
	 * @return static
	 */
	public static function createFromDbArray(array $data)
	{
		// Bylo v 1 případě z asi 15k vzorků, raději to ošetřím, nullable to není, ten sem tam nenašel tak dám 0
		$data['country'] = $data['country'] === 'null' ? 0 : $data['country'];
		return parent::createFromDbArray($data);
	}

	public function getParticipantsArray(): array
	{
		if (empty($this->participants)) {
			return [];
		}

		return unserialize($this->participants);
	}

	/**
	 * @return ParticipantCesys[]
	 */
	public function getParticipants(bool $reset = false): array
	{
		if ($reset) {
			unset($this->participantObjects);
		}
		if (isset($this->participantObjects)) {
			return $this->participantObjects;
		}

		$this->participantObjects = [];
		foreach ($this->getParticipantsArray() as $participantArray) {
			$this->participantObjects[] = ParticipantCesys::createFromArray($participantArray);
		}

		return $this->participantObjects;
	}

	public function getOfferDetailArray(): array
	{
		return unserialize($this->offerDetail) ?? [];
	}

	public function getOfferDetail(bool $reset = false): OfferDetailCesys
	{
		if ($reset) {
			unset($this->offerDetailObject);
		}
		if (isset($this->offerDetailObject)) {
			return $this->offerDetailObject;
		}

		return $this->offerDetailObject = OfferDetailCesys::createFromArray($this->getOfferDetailArray());
	}

	public function getServicesArray(): array
	{
		if (empty($this->services)) {
			return [];
		}

		return unserialize($this->services);
	}

	/**
	 * @return ServiceCesys[]
	 */
	public function getServices(bool $reset = false): array
	{
		if ($reset) {
			unset($this->serviceObjects);
		}
		if (isset($this->serviceObjects)) {
			return $this->serviceObjects;
		}


		$this->serviceObjects = [];
		foreach ($this->getServicesArray() as $serviceArray) {
			$this->serviceObjects[] = ServiceCesys::createFromArray($serviceArray);
		}

		return $this->serviceObjects;
	}

	/**
	 * Spočítá cena, za kterou TO, který organizuje zájezd, prodává
	 * Tj. v tomto nejsou započítány služby přidávané prodejcem
	 * @return float
	 */
	public function getTotalPriceFromPrices(): float
	{
		$totalPrice = 0;
		$offerDetail = $this->getOfferDetail();
		foreach ($offerDetail->prices as $roomPrices) {
			foreach ($roomPrices as $price) {
				if ($price->perDay) {
					$totalPrice += $price->price * $price->count * $offerDetail->duration;
				} else {
					$totalPrice += $price->price * $price->count;
				}

			}
		}
		// I blbé součty floatů mohou vést k nepřesnému výsledku, v db je totalPrice (10,2), takže na 2 místa
		return round($totalPrice, 2);
	}

	/**
	 * Kompletní koncová cena pro koncového klienta
	 * @return float
	 */
	public function getTotalPrice(): float
	{
		$totalPrice = $this->getTotalPriceFromPrices();
		foreach ($this->getServices() as $service) {
			$totalPrice += $service->price;
		}
		// I blbé součty floatů mohou vést k nepřesnému výsledku, v db je totalPrice (10,2), takže na 2 místa
		return round($totalPrice, 2);
	}

	public function getClientName(): string
	{
		return trim(trim($this->firstname) . ' ' . trim($this->name));
	}
}