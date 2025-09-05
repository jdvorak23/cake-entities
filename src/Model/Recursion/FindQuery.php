<?php

namespace Cesys\CakeEntities\Model\Recursion;

use Cesys\CakeEntities\Model\Find\Cache;
use Cesys\CakeEntities\Model\Find\CakeParams;
use Cesys\CakeEntities\Model\Find\FindParams;
use Cesys\CakeEntities\Model\Find\EntityCache;

/**
 * @internal
 */
class FindQuery extends Query
{
	public FindParams $findParams;

	/**
	 * Params původního uživatelova volání
	 * Nemusí existovat, pokud první volání je systémové (přes getEntities)
	 * @var CakeParams
	 */
	private CakeParams $originalParams;

	public Cache $cache;

	/**
	 * @var FindParams[]
	 */
	private array $activeFindParamsPath = [];

	private bool $isInFindParamsRecursion = false;

	public bool $useCache;

	public function __construct(array $fullContains, bool $useCache, ?CakeParams $originalParams = null)
	{
		$this->useCache = $useCache;
		if ($originalParams !== null) {
			$this->originalParams = $originalParams;
		}
		$this->findParams = FindParams::create($fullContains, $useCache);
	//bdump($this->findParams, 'FINAL Contains');
		$this->cache = new Cache($useCache);
	}

	public function findStart(string $modelClass, ?callable $endCallback = null)
    {
		$this->start($modelClass, $endCallback);
		Timer::start($modelClass . count($this->path));
		$findParams = $this->getFindParamsInPath();
		// Do $activeFindParamsPath připřadíme až poté, při vyhledávání isInFindParamsRecursion potřebujeme activeFindParamsPath ještě bez přidaných FindParams
		$isStartingRecursion = ! $this->isInFindParamsRecursion && in_array($findParams, $this->activeFindParamsPath, true);

		if ($this->isInFindParamsRecursion || $isStartingRecursion) {
			// Pokud jsme v rekurzi, bude model použit +1krát
			$findParams->willBeUsed++;
		}

		if ($isStartingRecursion) {
			$this->isInFindParamsRecursion = true;
			$this->addModelEndCallback(function () use ($findParams) {
				$this->isInFindParamsRecursion = false;
			});
		}
		$this->activeFindParamsPath[] = $findParams;
		//bdump("dd");
//$this->cache->setEntityCache($findParams); todo asi neni potreba getter zinit?
    }

    public function findEnd(): bool
    {
		Timer::stop();
		array_pop($this->activeFindParamsPath);
		return $this->end();
    }

	public function isSystemCall(): bool
	{
		// Pokud nejsou setnuté $originalParams, je i první volání systémové
		return ! $this->isOriginalCall() || ! isset($this->originalParams);
	}

	public function containsSameModel(?FindParams $findParams = null): bool
	{
		$findParams = $findParams ?? $this->getFindParams();
		if (isset($findParams->contains[$findParams->modelClass])) {
			// Rekurze do vl. tabulky
			return true;
		}

		return false;
	}

	public function isInFindParamsRecursion(): bool
	{
		return $this->isInFindParamsRecursion;
	}


	public function isChildModelInFindParamsRecursion(string $childModelClass): bool
	{
		if ($this->isInFindParamsRecursion()) {
			return true;
		}

		$findParams = $this->getFindParams();
		$childModelFindParams = $findParams->getContainedFindParams($childModelClass);
		if ($childModelFindParams === $findParams) {
			// Pro urychlení
			return true;
		}

		return in_array($childModelFindParams, $this->activeFindParamsPath, true);
	}


	/**
	 * @param FindParams|null $findParams
	 * @return bool
	 *  todo asi shit smazat
	 */
	public function isRecursiveToSelfEndlessCacheCompatible(?FindParams $findParams = null): bool
	{
		$findParams = $findParams ?? $this->getFindParams();
		return $findParams->isRecursiveToSelfEndlessCacheCompatible();
		/*static $query;
		static $pathCache;
		if ( ! isset($query)) {
			$query = new Query();
			$query->onEnd[] = function () use (&$query) {
				$query = null;
			};
			$pathCache = [];
		}
		$query->start($findParams->modelClass);

		if (isset($pathCache[$findParams->getId()])) {
			$query->end();
			return true;
		}
		$pathCache[$findParams->getId()] = true;

		if ( ! $childFindParams = $findParams->contains[$findParams->modelClass] ?? null) {
			$query->end();
			return false;
		}

		if ( ! $findParams->isCacheCompatibleWith($childFindParams)) {
			$query->end();
			return false;
		}

		$return = $this->isRecursiveToSelfEndlessCacheCompatible($childFindParams);
		$query->end();
		return $return;*/
	}

	public function getEntityCache(?FindParams $findParams = null): EntityCache
	{
		return $this->cache->getEntityCache($findParams ?? $this->getFindParams());
	}


	public function getOriginalParams(): CakeParams
	{
		return $this->originalParams;
	}


	/**
	 * Vrátí část větve původních FindParams, které odpovídají aktuální cestě - nebo aktuální cestě + $appendToPath
	 * Musí se volat poprvé ve findStart() ještě před
	 * @param array $appendToPath
	 * @return FindParams
	 */
	public function getFindParams(array $appendToPath = []): FindParams
	{
		if ($appendToPath) {
			return $this->getFindParamsInPath($appendToPath);
		}

		return $this->activeFindParamsPath[array_key_last($this->activeFindParamsPath)];
	}

	/**
	 * @param array $appendToPath
	 * @return FindParams
	 */
	private function getFindParamsInPath(array $appendToPath = []): FindParams
	{
		$findParams = $this->findParams;
		$path = array_merge($this->activePath, (array_values($appendToPath)));
		array_shift($path);
		foreach ($path as $modelClass) { // Todo mrknout jen na předchozí ??
			$findParams = $findParams->getContainedFindParams($modelClass);
		}

		return $findParams;
	}


	public function getBackwardContains()
	{
		$interestedModel = $this->getCurrentModelClass();

		$result = [];
		foreach ($this->getFindParams()->contains as $modelClass => $contains) {
			/*if ($modelClass === $interestedModel) {
				continue;
			}*/
			foreach ($contains->contains as $modelContains) {
				if ($modelContains->modelClass === $interestedModel && $this->getFindParams() === $modelContains) {
					$result[] = $modelClass;
				}
			}

		}

		return $result;
	}

}