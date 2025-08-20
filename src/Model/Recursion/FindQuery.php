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

	private array $activeContainsPath = [];

	private bool $isInContainsRecursion = false;

	public function __construct(array $fullContains, bool $isFirstSystem)
	{
		$this->contains = Contains::create($fullContains);
		bdump($this->contains, 'FINAL Contains');
		$this->isFirstSystem = $isFirstSystem;
		$this->cache = new Cache();
	}

	public function findStart(string $modelClass)
    {
		$this->start($modelClass);
		// Volání $this->getFullContains() zároveň musí být pro init
		$fullContains = $this->getFullContains();
		if ( ! $this->isInContainsRecursion && in_array($fullContains, $this->activeContainsPath, true)) {
			$this->isInContainsRecursion = true;
			$this->addModelEndCallback(function () {
				$this->isInContainsRecursion = false;
			});
		}
		if ($this->isInContainsRecursion || in_array($fullContains, $this->activeContainsPath, true)) {
			if ( ! $this->isInContainsRecursion && in_array($fullContains, $this->activeContainsPath, true)) {
				bdump($this, 'COZEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEE XXXXXXXXXXXXXXx');
			}
			$fullContains->inNodesUsed++;
		}
		$this->activeContainsPath[] = $fullContains;
		$this->cache->setCache($fullContains);
    }

    public function findEnd(): bool
    {
		array_pop($this->activeContains);
		array_pop($this->activeContainsPath);
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

	public function isInContainsRecursion(): bool
	{
		return $this->isInContainsRecursion;
	}


	public function isChildModelInContainsRecursion(string $childModelClass): bool
	{
		if ($this->isInContainsRecursion()) {
			return true;
		}
		$contains = $this->getFullContains();
		if ( ! isset($contains->contains[$childModelClass])) {
			throw new \InvalidArgumentException("'$childModelClass' is not in contains of $contains->modelClass.");
		}

		if ($contains->contains[$childModelClass] === $contains) {
			// Pro urychlení
			return true;
		}

		return in_array($contains->contains[$childModelClass], $this->activeContainsPath, true);
	}


	public function isInSelfContainsRecursion(): bool
	{
		$activeContainsPath = $this->activeContainsPath;
		array_pop($activeContainsPath);
		return in_array($this->getFullContains(), $activeContainsPath, true);
	}


	/**
	 * @param Contains|null $contains
	 * @return bool
	 * @deprecated todo asi shit smazat
	 */
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
		return $this->cache->getEntityCache($contains ?? $this->getFullContains());
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