<?php

namespace Cesys\CakeEntities\Model\Find;

class ModelCache
{
	/**
	 * @var EntityCache[]
	 */
	private array $cache = [];

	private string $modelClass;

	public function __construct(string $modelClass)
	{
		$this->modelClass = $modelClass;
	}

	public function getModelClass(): string
	{
		return $this->modelClass;
	}


	public function getEntityCache(FindParams $findParams): ?EntityCache
	{
		return $this->cache[$findParams->getId()] ?? null;
	}


	/**
	 * @return EntityCache[]
	 */
	public function getCache(): array
	{
		return $this->cache;
	}


	/**
	 * Přidání nově vytořené EntityCache
	 * @param EntityCache $entityCache
	 * @return void
	 */
	public function addEntityCache(EntityCache $entityCache): void
	{
		$this->cache[$entityCache->findParams->getId()] = $entityCache;
	}


	/**
	 * Sloučení použité cache (po proběhlém findu) do globální cache
	 * @param ModelCache $modelCache
	 * @return void
	 */
	public function mergeCache(self $modelCache): void
	{
		$globalEntityCaches = [];
		foreach ($this->cache as $entityCache) {
			$globalEntityCaches[spl_object_id($entityCache)] = true;
		}
		$merged = [];
		$toMerge = [];
		foreach (array_reverse($modelCache->cache) as $entityCache) {
			if ($parentEntityCache = $entityCache->getParentEntityCache()) {
				// Pokud má parenta, rovnou ho vynecháme z merge => má sdílenou cache
				$merged[spl_object_id($parentEntityCache)] = true;
			}
			if (isset($merged[spl_object_id($entityCache)])) {
				// Je-li už v mergovaných, nebo se nemá mergovat
				continue;
			}
			$merged[spl_object_id($entityCache)] = true;
			if ($parentEntityCache && isset($globalEntityCaches[spl_object_id($parentEntityCache)])) {
				// Pokud má parenta už v globální cache, nebudeme přidávat, už tam je
				continue;
			}
			$toMerge[] = $entityCache;
		}

		foreach ($toMerge as $entityCache) {
			foreach ($this->cache as $globalEntityCache) {
				if ($entityCache->findParams->isCacheCompatibleWith($globalEntityCache->findParams)) {
					$globalEntityCache->appendFrom($entityCache);
					continue 2;
				}
			}
			$this->cache[$entityCache->findParams->getId()] = $entityCache;
		}



	}
}