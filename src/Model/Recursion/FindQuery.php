<?php

namespace Cesys\CakeEntities\Model\Recursion;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Cesys\CakeEntities\Model\Entities\EntityHelper;
use Cesys\CakeEntities\Model\Find\Cache;
use Cesys\CakeEntities\Model\Find\Contains;
use Cesys\CakeEntities\Model\Find\EntityCache;
use Cesys\CakeEntities\Model\Find\Stash;

/**
 * @internal
 */
class FindQuery extends Query
{
	public Contains $contains;

	/**
	 * @var Contains[]
	 */
	public array $activeContains = [];

	public Cache $cache;

	private bool $isFirstSystem;

	public function __construct(array $fullContains, bool $isFirstSystem)
	{
		bdump($fullContains);
		$this->contains = Contains::create($fullContains);
		$this->isFirstSystem = $isFirstSystem;
		$this->cache = new Cache();
		bdump($this->contains);
	}

	public function findStart(string $modelClass)
    {
		$this->start($modelClass);
		// Volání $this->getFullContains() zároveň musí být pro init
		$this->cache->setCache($this->getFullContains());
    }

    public function findEnd(): bool
    {
		array_pop($this->activeContains);
		return $this->end();
    }

	public function isSystemCall(): bool
	{
		return ! $this->isOriginalCall() || $this->isFirstSystem;
	}

	public function isRecursiveToSelf(?Contains $contains = null): bool
	{
		$contains = $contains ?? $this->getFullContains();
		if (isset($contains->contains[$contains->modelClass])) {
			// Rekurze do vl. tabulky
			return true;
		}

		return false;
	}

	public function isRecursionToSelfEndless(?Contains $contains = null): bool
	{
		$contains = $contains ?? $this->getFullContains();
		static $query;
		static $pathCache;
		if ( ! isset($query)) {
			$query = new Query();
			$query->onEnd[] = function () use (&$query) {
				$query = null;
			};
			$pathCache = [];
		}
		$query->start($contains->modelClass);

		if (isset($pathCache[$contains->getId()])) {
			$query->end();
			return true;
		}
		$pathCache[$contains->getId()] = true;


		if (empty($contains->contains[$contains->modelClass])) {
			$query->end();
			return false;
		}

		$return = $this->isRecursionToSelfEndless($contains->contains[$contains->modelClass]);
		$query->end();
		return $return;
	}

	public function getEntityCache(?Contains $contains = null): EntityCache
	{
		return $this->cache->getCache($contains ?? $this->getFullContains());
	}

	public function getStash(?Contains $contains = null): Stash
	{
		return $this->cache->getStash($contains ?? $this->getFullContains());
	}


	public function getFullContains(array $appendToPath = []): Contains
	{
		$firstCall = count($this->activePath) > count($this->activeContains);
		if ($appendToPath || $firstCall) {
			$contains = $this->contains;
			$path = array_merge($this->activePath, (array_values($appendToPath)));
			array_shift($path);
			foreach ($path as $modelClass) {
				$contains = $contains->contains[$modelClass];
			}
			if ($firstCall) {
				$this->activeContains[$this->getCurrentModelClass()] = $contains;
			}
		} else {
			$contains = $this->activeContains[$this->getCurrentModelClass()];
		}

		return $contains;
	}


	public function getBackwardContains()
	{
		$interestedModel = $this->getCurrentModelClass();

		$result = [];
		foreach ($this->getFullContains()->contains as $modelClass => $contains) {
			/*if ($modelClass === $interestedModel) {
				continue;
			}*/
			foreach ($contains->contains as $modelContains) {
				if ($modelContains->modelClass === $interestedModel && $this->getFullContains() === $modelContains) {
					$result[] = $modelClass;
				}
			}

		}

		return $result;
	}

}