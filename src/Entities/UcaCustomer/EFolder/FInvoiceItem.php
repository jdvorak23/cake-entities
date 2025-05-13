<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class FInvoiceItem extends CakeEntity
{

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