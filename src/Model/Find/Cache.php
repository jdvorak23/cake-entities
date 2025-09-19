<?php

namespace Cesys\CakeEntities\Model\Find;

use Cesys\CakeEntities\Model\GetModelStaticTrait;

class Cache
{
	use GetModelStaticTrait;
	/**
	 * @var ModelCache[]
	 */
	private array $cache = [];

	private bool $useGlobalCache;

	public function __construct(bool $useGlobalCache)
	{
		$this->useGlobalCache = $useGlobalCache;
	}


	public function getEntityCache(FindParams $findParams): EntityCache
	{
		if ($entityCache = $this->getModelCache($findParams->modelClass)->getEntityCache($findParams)) {
			return $entityCache;
		}
		$this->setEntityCache($findParams);

		return $this->getModelCache($findParams->modelClass)->getEntityCache($findParams);
	}


	public  function getModelCache(string $modelClass): ModelCache
	{
		return $this->cache[$modelClass] ??= new ModelCache($modelClass);
	}


	private function setEntityCache(FindParams $findParams): void
	{
		$modelCache = $this->getModelCache($findParams->modelClass);

		// Pokusíme se najít kompatibilní cache
		if ($entityCache = $modelCache->getCacheCompatibleEntityCache($findParams)) {
			// Pokud je nalezena, vytvoříme sdílenou
			$modelCache->addEntityCache(EntityCache::createFrom($entityCache, $findParams));
			return;
		}

		// Pokud není nalezena, a pokud se má použít globální cache, pokusíme se najít kompatibilní
		if ($this->useGlobalCache) {
			/** @var ModelCache $globalCache */
			$globalCache = static::getModelStatic($findParams->modelClass)->getModelCache();
			if ($entityCache = $globalCache->getCacheCompatibleEntityCache($findParams)) {
				// Pokud je nalezena, vytvoříme sdílenou
				$modelCache->addEntityCache(EntityCache::createFrom($entityCache, $findParams));
				return;
			}
		}

		$modelCache->addEntityCache(new EntityCache($findParams));
	}

}