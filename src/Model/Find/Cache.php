<?php

namespace Cesys\CakeEntities\Model\Find;

class Cache
{
	/**
	 * @var EntityCache[][]
	 */
	private array $cache = [];

	public function setCache(Contains $contains)
	{
		if ( ! isset($this->cache[$contains->modelClass])) {
			// První cache modelu, vytvoří se stash
			$this->cache[$contains->modelClass]['stash'] = new Stash();
		}

		if ($this->cache[$contains->modelClass][$contains->getId()] ?? null) {
			// Pro sichr už existuje
			return;
		}

		if (count($this->cache[$contains->modelClass]) > 1) {
			// 'stash' index uz tam je, koukáme jestli je další
			$cache = $this->cache[$contains->modelClass];
			unset($cache['stash']);
			$cache = array_reverse($cache, true);
			foreach ($cache as $containsId => $entityCache) {
				if ($contains->params->isEqualTo($entityCache->contains->params)) {
					$newCache = clone $entityCache;
					$newCache->contains = $contains;
					$this->cache[$contains->modelClass][$contains->getId()] = $newCache;
					return;
				}
			}
		}



		$this->cache[$contains->modelClass][$contains->getId()] = new EntityCache($contains, $this->cache[$contains->modelClass]['stash']);
	}

	public function getCache(): array
	{
		return $this->cache;
	}

	public function getEntityCache(Contains $contains): EntityCache
	{
		if ( ! isset($this->cache[$contains->modelClass][$contains->getId()])) {
			$this->setCache($contains);
		}
		return $this->cache[$contains->modelClass][$contains->getId()];
	}

	public function getStash(Contains $contains): Stash
	{
		return $this->cache[$contains->modelClass]['stash'];
	}
}