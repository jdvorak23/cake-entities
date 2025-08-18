<?php

namespace Cesys\CakeEntities\Model\Find;

use Cesys\CakeEntities\Model\Entities\CakeEntity;

class Stash
{
	private array $cache = [];

	private bool $sorted = false;

	public function add(CakeEntity $entity)
	{
		$id = $entity->getPrimary();
		if (isset($this->cache[$id])) {
			// Přepisujeme? asi  by se ani nemělo stávat / určitě ... ?
			if ($entity !== $this->cache[$id]) {
				bdump($entity !== $this->cache[$id], "Přepis entity BUUUUUUUUUUUUUUUUUUUUUUUUUu !!!");
				bdump($this->cache[$id]);
				bdump($entity);
			}
			return;
		}
		$this->cache[$id] = $entity;
		$this->sorted = false;
	}

	public  function &getCache(): array
	{
		if ( ! $this->sorted) {
			ksort($this->cache);
			$this->sorted = true;
		}

		return $this->cache;
	}
}