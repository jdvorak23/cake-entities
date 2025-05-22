<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;

class FSubjectBank extends CakeEntity
{
	const CZ_CZK = 10;
	const CZ_EUR = 20;

	const HU_HUF = 30;

	const HU_EUR = 40;

	public int $id;

	public int $fSubjectId;

	public ?string $name;

	public ?string $account;

	public ?string $iban;

	public ?string $swift;

	public $token;

	public $active;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfFSubjectBank';
	}

	public function getAccountNumber(): ?string
	{
		if ( ! isset($this->account)) {
			return null;
		}
		$parts = explode('/', $this->account);
		return $parts[0];
	}

	public function getBankCode(): ?string
	{
		if ( ! isset($this->account)) {
			return null;
		}
		$parts = explode('/', $this->account);
		return $parts[1] ?? '';
	}

}