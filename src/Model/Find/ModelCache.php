<?php

namespace Cesys\CakeEntities\Model\Find;

use Cesys\CakeEntities\Model\GetModelStaticTrait;

class ModelCache
{
	use GetModelStaticTrait;
	/**
	 * První klíč je useDbConfig
	 * Druhý klíč je spl_object_id FindParams
	 * @var EntityCache[][]
	 */
	private array $cache;

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
		return $this->cache[$findParams->getUseDbConfig()][$findParams->getId()] ?? null;
	}


	public function getCacheCompatibleEntityCache(FindParams $findParams): ?EntityCache
	{
		foreach ($this->cache[$findParams->getUseDbConfig()] ?? [] as $entityCache) {
			if ($findParams->isCacheCompatibleWith($entityCache->findParams)) {
				return $entityCache;
			}
		}

		return null;
	}


	/**
	 * Přidání nově vytořené EntityCache
	 * @param EntityCache $entityCache
	 * @return void
	 */
	public function addEntityCache(EntityCache $entityCache): void
	{
		$this->cache[$entityCache->getUseDbConfig()][$entityCache->findParams->getId()] = $entityCache;
	}


	/**
	 * Sloučení použité cache (po proběhlém findu) do globální cache
	 * @param ModelCache $modelCache Cache použitá ve findu, tj. data z ní budeme "přepisovat" do static (což by měla být globální model cache)
	 * @return void
	 */
	public function mergeCache(self $modelCache): void
	{
		$globalEntityCachesList = [];
		foreach ($this->cache ?? [] as $useDbConfig => $entityCaches) {
			foreach ($entityCaches as $entityCache) {
				$globalEntityCachesList[spl_object_id($entityCache)] = true;
			}
		}

		$mergedList = [];
		$toMerge = [];
		foreach ($modelCache->cache as $useDbConfig => $entityCaches) {
			foreach (array_reverse($entityCaches) as $entityCache) {
				if ($parentEntityCache = $entityCache->getParentEntityCache()) {
					// Pokud má parenta, rovnou ho vynecháme z merge => má sdílenou cache
					$mergedList[spl_object_id($parentEntityCache)] = true;
				}
				if (isset($mergedList[spl_object_id($entityCache)])) {
					// Je-li už v mergovaných, nebo se nemá mergovat
					continue;
				}
				$mergedList[spl_object_id($entityCache)] = true;
				if ($parentEntityCache && isset($globalEntityCachesList[spl_object_id($parentEntityCache)])) {
					// Pokud má parenta už v globální cache, nebudeme přidávat, už tam je
					continue;
				}
				$toMerge[] = $entityCache;
			}
		}


		foreach ($toMerge as $entityCache) {
			$entityCacheUseDbConfig = $entityCache->getUseDbConfig();
			foreach ($this->cache ?? [] as $useDbConfig => $globalEntityCaches) {
				if ($useDbConfig !== $entityCacheUseDbConfig) {
					continue;
				}
				foreach ($globalEntityCaches as $globalEntityCache) {
					if ($entityCache->findParams->isCacheCompatibleWith($globalEntityCache->findParams)) {
						$globalEntityCache->appendFrom($entityCache);
						continue 3;
					}
				}
			}

			$this->cache[$entityCacheUseDbConfig][$entityCache->findParams->getId()] = $entityCache;
		}
	}
}