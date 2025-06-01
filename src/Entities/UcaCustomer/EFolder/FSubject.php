<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class FSubject extends CakeEntity
{

	public int $id;

	public $name;

	public $supplier;

	public $customer;

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

	public $guid;

	public $specificSymbol;

	public $taxpayer;

	public $tradesman;

	public $commissionContract;

	public $note;

	public $active;

	/**
	 * @var FSubjectAddress[] f_subject_id
	 */
	public array $fSubjectAddresses;


	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfFSubject';
	}

	public function getFSubjectAddress(): ?FSubjectAddress
	{
		if ($this->fSubjectAddresses) {
			return current($this->fSubjectAddresses);
		}

		return null;
	}
}