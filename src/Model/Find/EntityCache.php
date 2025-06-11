<?php

namespace Cesys\CakeEntities\Model\Find;

class EntityCache
{
	private array $indexes;

	private string $primaryColumn;

	public function __construct(string $primaryColumn = 'id')
	{
		$this->primaryColumn = $primaryColumn;
		$this->indexes[$primaryColumn] = [];
	}

	public function getPrimaryColumn(): string
	{
		return $this->primaryColumn;
	}


}