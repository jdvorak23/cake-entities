<?php

namespace Cesys\CakeEntities\Model\Find;

use Cesys\CakeEntities\Model\Recursion\Query;

class Contains
{
	/**
	 * @var static[]
	 */
	public array $contains = [];

	public Params $params;

	public string $modelClass;

	public int $inNodesUsed = 0;

	private array $nextUsedIndexes = [];

	public function __construct(string $modelClass)
	{
		$this->modelClass = $modelClass;
	}

	public function hasNU1(): bool
	{
		return $this->inNodesUsed === 1;
	}

	public function hasNextStandardFind(): bool
	{
		return $this->inNodesUsed > 1;
	}

	public function skipUse(): void
	{
		$this->inNodesUsed--;
	}

	public function afterFind(): void
	{
		$this->inNodesUsed--;
		$this->nextUsedIndexes = [];
	}

	public function setNextUsedIndex(string $index): void
	{
		$this->nextUsedIndexes[$index] = $index;
	}

	public function getNextUsedIndexes(): array
	{
		return $this->nextUsedIndexes;
	}

	public function getId(): int
	{
		return spl_object_id($this);
	}

	public static function create(array $contains): self
	{
		if (count($contains) !== 1) {
			throw new \Exception('Contains can only have one element');
		}

		$modelClass = array_key_first($contains);

		static $query;
		static $pathCache;
		/** @var static[][] $modelsContains */
		if ( ! isset($query)) {
			$query = new Query();
			$pathCache = [];
			$query->onEnd[] = function (self $instance) use (&$query) {
				$query = null;
				static::replaceIdentical($instance);
			};
		}
		$query->start($modelClass);

		if ($contains[$modelClass]['contains'] === null) {
			// null znamená, že jsme v 'rekurzi', tj. v přímé cestě 'nahoru' už je stejný contains => přiřadíme
			$index = array_search($modelClass, $query->activePath, true);
			$query->end(); // Zde query určitě není nikdy na konci (vždy false)
			return $pathCache[$index];
		}

		$pathCache[] = $instance = new self($modelClass);
		$params = $contains[$modelClass];
		unset($params['contains']);
		$instance->params = Params::create($params);

		foreach ($contains[$modelClass]['contains'] as $containedModelClass => $modelContains) {
			$instance->contains[$containedModelClass] = self::create([$containedModelClass => $modelContains]);
		}

		array_pop($pathCache);
		$query->end($instance);

		return $instance;
	}


	/**
	 * @param Contains $contains
	 * @return bool
	 */
	public function isEqualTo(self $contains): bool
	{
		//bdump($this);
		//bdump($contains);
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

		if ($this->modelClass !== $contains->modelClass) {
			$query->end();
			return false;
		}

		if ( ! $this->params->isEqualTo($contains->params)) {
			$query->end();
			return false;
		}

		if (
			count($this->contains) !== count($contains->contains)
			|| array_diff_key($this->contains, $contains->contains)
		) {
			//bdump(count($this->contains), "chcip");
			//bdump(count($contains->contains));
			$query->end();
			return false;
		}

		foreach ($this->contains as $modelClass => $modelContains) {
			if ( ! $modelContains->isEqualTo($contains->contains[$modelClass])) {
				$query->end();
				return false;
			}
		}

		$query->end();
		return true;
	}




	private static function replaceIdentical(Contains $contains): void
	{
		bdump($contains, 'UNREPLACED Contains');
		$cache = [];
		$queue = new \SplQueue();
		$queue[] = $contains;
		$containsToAppend = [];
		foreach ($queue as $contains) {
			if (isset($cache[$contains->modelClass][$contains->getId()])) {
				// Stejný node, který už jsme prošli / procházíme, na jiném místě ve stromě
				continue;
			}

			/** @var static $sameModelContains */
			foreach ($cache[$contains->modelClass] ?? [] as $sameModelContains) {
				if ($sameModelContains->isEqualTo($contains)) {
					$sameModelContains->inNodesUsed++;
					$containsToAppend[$contains->getId()]->contains[$contains->modelClass] = $sameModelContains;
					continue 2;
				}
			}

			$cache[$contains->modelClass][$contains->getId()] = $contains;
			$contains->inNodesUsed++;

			foreach ($contains->contains as $modelContains) {
				$containsToAppend[$modelContains->getId()] = $contains;
				$queue[] = $modelContains;
			}
		}
	}

}