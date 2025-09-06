<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class FInvoiceType extends CakeEntity
{
	/**
	 * Toto jsou typy jen pro Delta, zde v namespace EFolder, která je jen pro Delta jsou správně, v IFInvoiceType tyto být nemají
	 */
	const Issued = 1;
	const F = 10;
	const U = 11;
	const E = 12;

	public int $id;
	public ?string $prefix;
	public static function getModelClass(): string
	{
		return 'EfFInvoiceType';
	}
}