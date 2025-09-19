<?php

namespace Cesys\CakeEntities\Model\Recursion;

use Cesys\Utils\Arrays;

class Query
{
	public array $path = [];

	public array $activePath = [];

	public array $onEnd = [];

	private array $onModelEnd = [];


	public function start(string $modelClass, ?callable $endCallback = null)
	{
		$this->path[] = $modelClass;
		$this->activePath[] = $modelClass;
		$this->onModelEnd[] = [];
		if ($endCallback) {
			$this->addModelEndCallback($endCallback);
		}
	}


	public function end(): bool
	{
		array_pop($this->activePath);
		$callbacks = array_pop($this->onModelEnd);
		Arrays::invoke($callbacks);
		$isEnd = ! $this->activePath;
		if ($isEnd) {
			Arrays::invoke($this->onEnd);
		}

		return $isEnd;
	}

	public function addModelEndCallback(callable $callback)
	{
		$this->onModelEnd[array_key_last($this->onModelEnd)][] = $callback;
	}


	public function isOriginalCall(): bool
	{
		return count($this->activePath) < 2;
	}


	public function getCurrentModelClass(): ?string
	{
		return $this->activePath[array_key_last($this->activePath)];
	}


	public function isFirstModelCall(): bool
	{
		$path = $this->path;
		array_pop($path);
		return ! in_array($this->getCurrentModelClass(), $path, true);

	}

	public function isFirstModelInActivePath(): bool
	{
		$activePath = $this->activePath;
		array_pop($activePath);
		return ! in_array($this->getCurrentModelClass(), $activePath, true);
	}

}