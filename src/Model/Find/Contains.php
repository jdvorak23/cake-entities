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

	public function __construct(string $modelClass)
	{
		$this->modelClass = $modelClass;
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
		static $modelsContains;
		if ( ! isset($query)) {
			$query = new Query();
			$pathCache = [];
			$modelsContains = [];
		}
		$query->start($modelClass);

		if ($contains[$modelClass]['contains'] === null) {
			$index = array_search($modelClass, $query->activePath);
			$query->end();
			return $pathCache[$index];
		}

		$pathCache[] = $instance = new self($modelClass);
		$params = $contains[$modelClass];
		unset($params['contains']);
		$instance->params = Params::create($params);

		foreach ($contains[$modelClass]['contains'] as $containedModelClass => $modelContains) {
			$instance->contains[$containedModelClass] = self::create([$containedModelClass => $modelContains]);
		}

		$foundSimilar = false;
		if (isset($modelsContains[$modelClass])) {
			foreach ($modelsContains[$modelClass] as $otherModelContains) {
				if ($otherModelContains->isEqualTo($instance)) {
					$instance = $otherModelContains;
					$foundSimilar = true;
					break;
				}
			}
		}

		if ( ! $foundSimilar) {
			$modelsContains[$modelClass][] = $instance;
		}

		array_pop($pathCache);
		if ($query->end()) {
			$query = null;
		}

		return $instance;
	}

	/*public function getContains(): ?array
	{
		return $this->toArray()['contains'];
	}

	public function toArray(): ?array
	{
		static $query;
		static $cache;
		if ( ! isset($query)) {
			$query = new Query();
			$cache = [];
		}
		$query->start($this->modelClass);
		$objId = spl_object_id($this);
		if (isset($cache[$objId])) {
			$query->end();
			return null;
		}

		$cache[$objId] = 1;

		$params = $this->params->toArray();
		$params['contains'] = [];
		foreach ($this->contains as $modelClass => $modelContains) {
			$params['contains'][$modelClass] = $modelContains->toArray();
		}

		if ($query->end()) {
			$query = null;
		}

		return $params;
	}*/

	/**
	 * @param Contains $contains
	 * @return bool
	 */
	public function isEqualTo(self $contains): bool
	{
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

}