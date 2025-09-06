<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class FSubjectBank extends CakeEntity
{
	public int $id;
	public int $fSubjectId;

	public ?int $fCurrencyId;

	public ?string $name;

	public ?string $account;

	public ?string $iban;

	public ?string $swift;

	public ?string $apiName;
	public ?string $token;

	public ?bool $active;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'FSubjectBank';
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