<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class Log extends CakeEntity
{
	const OperationCreate = 'create';
	const OperationUpdate = 'update';
	const OperationDelete = 'delete';


	public int $id;

	public string $database;

	public string $table;

	public string $operation;

	public int $primary;

	public ?string $data;

	public ?string $diff;

	public int $userId;

	public ?DateTime $created;

	public User $user;

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfLog';
	}

	public function getData(): ?array
	{
		if ($this->data === null) {
			return null;
		}
		return unserialize($this->data);
	}

	public function setData(?array $data): void
	{
		$this->data = $data === null ? null : serialize($data);
	}

	public function getItems(): string
	{
		if ( ! $data = $this->getData()) {
			return '-';
		}
		$result = '';
		foreach ($data as $key => $value) {
			if ($value === null) {
				continue;
			}
			$result .= "\n$key: $value";
		}
		return trim($result);
	}

}