<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class FSubject extends CakeEntity
{

	public int $id;

	public ?string $name;

	public ?bool $supplier;

	public ?bool $customer;

	public ?string $inumber;

	public ?string $tnumber;

	public $itnumber;

	public $address;

	public $contactPerson;

	public $email;

	public $phone;

	public $emailInvoice;

	public $phoneInvoice;

	public $sendInvoice;

	public $remindInvoice;

	public $registration;

	public string $guid;

	public ?string $specificSymbol;

	public bool $taxpayer;

	public $tradesman;

	public $commissionContract;

	public $note;

	public ?bool $active;

	/**
	 * @var FSubjectAddress id fSubjectId
	 */
	public FSubjectAddress $fSubjectAddress;


	public static function getModelClass(): string
	{
		return 'EfFSubject';
	}

}