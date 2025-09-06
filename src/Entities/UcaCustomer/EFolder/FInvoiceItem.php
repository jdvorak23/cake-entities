<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class FInvoiceItem extends CakeEntity
{
	/**
	 * Typy f_items (fItemId)
	 */
	const TypeCommission = 1;
	const TypeService = 2;
	const TypeFOfEInvoice = 10; // Položka typu F na faktuře E
	const TypeUOfEInvoice = 11; // Položka typu U na faktuře E
	const TypeDeposit = 30;

	public int $id;
	
	public ?int $fItemId;
	
	public int $fVatTypeId;
	
	public int $fInvoiceId;
	
	public ?string $name;
	
	public ?bool $onlyText;
	
	public ?float $price;
	
	public ?float $priceWithVat;

	/**
	 * default: 1
	 * @var int|null
	 */
	public ?int $quantity;
	
	public ?string $quantityUnit;
	
	public ?float $vatBase;
	
	public ?float $vat;
	
	public ?float $total;
	
	public ?int $position;
	
	public ?bool $active;

	public static function getModelClass(): string
	{
		return 'EfFInvoiceItem';
	}
}