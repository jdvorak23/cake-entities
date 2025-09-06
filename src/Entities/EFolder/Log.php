<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\EFolder\Interfaces\ILog;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class Log extends CakeEntity implements ILog
{
	public int $id;

	public string $database;

	public string $table;

	public string $operation;

	public int $primary;

	/**
	 *
	 * @var string|null
	 */
	public ?string $data;

	public ?string $diff;

	public int $userId;

	public ?DateTime $created;

	public User $user;

	public static function getModelClass(): string
	{
		return 'EfLog';
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

	public function getDiff(): ?array
	{
		if ($this->diff === null) {
			return null;
		}
		return unserialize($this->diff);
	}

	public function setDiff(?array $diff): void
	{
		$this->diff = $diff === null ? null : serialize($diff);
	}

	public function getItems(array &$logData): array
	{
		$data = $this->getData();
		$diff = $this->getDiff();
		if ($data !== null && $diff !== null) {
			// live
			return [$diff, $data];
		} elseif ($data !== null) {
			// Celý řádek
			$logData = $data;
			return [$data, []];
		} elseif ($diff !== null) {
			$previous = [];
			foreach ($diff as $column => $value) {
				if (array_key_exists($column, $logData)) {
					$previous[$column] = $logData[$column];
				}
				$logData[$column] = $value;
			}
			return [$diff, $previous];
		}


		bdump("WHAAAAAAAAAT");
		return [];
	}

}