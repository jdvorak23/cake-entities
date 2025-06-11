<?php

namespace Cesys\CakeEntities\Model\Recursion;

class Query
{
	public array $path = [];

	public array $activePath = [];


	public function start(string $modelClass)
	{
		$this->path[] = $modelClass;
		$this->activePath[] = $modelClass;
	}


	public function end(): bool
	{
		array_pop($this->activePath);
		return ! $this->activePath;
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


	public function isOriginalCall(): bool
	{
		return count($this->activePath) < 2;
	}
}