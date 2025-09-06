<?php

namespace Cesys\CakeEntities\Model\Find;

use Cesys\CakeEntities\Model\GetModelTrait;

class Cache
{
	use GetModelTrait;
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
		foreach (array_reverse($modelCache->getCache()) as $entityCache) {
			if ($findParams->isCacheCompatibleWith($entityCache->findParams)) {
				$modelCache->addEntityCache(EntityCache::createFrom($entityCache, $findParams));
				return;
			}
		}

		if ($this->useGlobalCache) {
			$globalCache = $this->getModel($findParams->modelClass)->getModelCache();
			/** @var EntityCache $entityCache */
			foreach ($globalCache->getCache() as $entityCache) {
				if ($findParams->isCacheCompatibleWith($entityCache->findParams)) {
					$modelCache->addEntityCache(EntityCache::createFrom($entityCache, $findParams));
					return;
				}
			}
		}

		$modelCache->addEntityCache(new EntityCache($findParams));
	}


	/*public function getModelCache(string $modelClass): array
	{
		return $this->cache[$modelClass] ?? [];
	}*/

	public function getCache(): array
	{
		return $this->cache;
	}

}