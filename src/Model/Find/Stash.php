<?php

namespace Cesys\CakeEntities\Model\Find;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class Stash
{
	private array $cache = [];

	public function add(CakeEntity $entity)
	{
		$id = $entity->getPrimary();
		if (isset($this->cache[$id])) {
			// Přepisujeme? asi  by se ani nemělo stávat
			bdump($entity === $this->cache[$id], "Přepis entity");
			$this->cache[$id] = $entity;
			return;
		}
		$cache = [];
		$appended = false;
		foreach ($this->cache as $otherId => $otherEntity) {
			if ($otherId > $id) {
				$appended = true;
				$cache[$id] = $entity;
			}
			$cache[$otherId] = $otherEntity;
		}
		if ( ! $appended) {
			$cache[$id] = $entity;
		}

		$this->cache = $cache;
	}

	public  function &getCache(): array
	{
		return $this->cache;
	}
}