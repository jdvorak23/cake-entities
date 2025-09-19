<?php

namespace Cesys\CakeEntities\Entities\Interfaces;

interface IBaseCurrency
{
	public function round(float $amount): float;

	public function getRoundCount(): int;

	public function getCode(): string;

	public function getId(): int;
}