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


	public function addEntityCache(EntityCache $entityCache): void
	{
		// todo projít a nejak sloučit při kompatibilite?
		$this->cache[$entityCache->findParams->getId()] = $entityCache;
	}
}