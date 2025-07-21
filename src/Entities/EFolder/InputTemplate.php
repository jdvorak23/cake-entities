<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class InputTemplate extends CakeEntity
{
    public int $id;

	public ?int $parentId;

    public string $company;

    public ?string $className;

	public bool $isParent;
	public bool $construction;
	public bool $active;

    public ?DateTime $created;

    public ?DateTime $modified;

	public ?InputTemplate $parent;

    /**
     * @var InputTemplateProperty[] input_template_id
     */
    public array $properties;


	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfInputTemplate';
	}

	/**
	 * @return InputTemplateProperty[]
	 */
    public function getInputTemplateProperties(): array
    {
		// Chceme mít na začátku ty s hodnotou, to jsou ty, které se (u stejného korporátu) jediné liší
		// Tj. performance, aby se co nejrychleji vyloučily ty, co nechceme
		$withValue = [];
		$rest = [];
		foreach ($this->properties as $property) {
			if ($property->value !== null) {
				$withValue[$property->id] = $property;
			} else {
				$rest[$property->id] = $property;
			}
		}
		$result = $withValue + $rest;
		if (isset($this->parent)) {
			$result = $result + $this->parent->getInputTemplateProperties();
		}
        return $result;
    }

	/**
	 * @param string $text
	 * @param bool $interruptOnError
	 * @return bool true -> všechny property byly setnuté, false -> bylo interupnuto
	 */
	public function setPropertiesFrom(string $text, bool $interruptOnError = false): bool
	{
		foreach ($this->getInputTemplateProperties() as $inputTemplateProperty) {
			if ( ! $inputTemplateProperty->hasSearchedValue()) {
				$inputTemplateProperty->setValueFrom($text);
				if ($interruptOnError && $inputTemplateProperty->hasError()) {
					return false;
				}
			}
		}
		return true;
	}


	public function getErrorsCount(): int
	{
		$errorsCount = 0;
		foreach ($this->getInputTemplateProperties() as $inputTemplateProperty) {
			if ($inputTemplateProperty->hasError()) {
				$errorsCount++;
			}
		}
		return $errorsCount;
	}

	public function getParentsBreadcrumbs(): string
	{
		$isClientCall = __FUNCTION__ !== debug_backtrace(!DEBUG_BACKTRACE_PROVIDE_OBJECT|DEBUG_BACKTRACE_IGNORE_ARGS,2)[1]['function'];
		if ( ! $this->parent) {
			return $isClientCall ? '-' : ($this->company . " ($this->id)");
		}
		$result = '';
		if ( ! $isClientCall) {
			$result .= ($this->company . " ($this->id)") . ' -> ';
		}
		$result .= $this->parent->getParentsBreadcrumbs();

		return $result;
	}

	/**
	 * @return InputTemplateProperty[]
	 */
	public function getInputTemplatePropertiesTree(): array
	{
		$withValue = [];
		$rest = [];
		foreach ($this->properties as $property) {
			if ($property->value !== null) {
				$withValue[$property->id] = $property;
			} else {
				$rest[$property->id] = $property;
			}
			$property->children = [];
		}
		$allProperties = $withValue + $rest;

		$topProperties = [];
		/** @var InputTemplateProperty $property */
		foreach ($allProperties as $property) {
			if ( ! $property->parent) {
				$topProperties[$property->id] = $property;
			} else {
				$property->parent->children[$property->id] = $property;
			}
		}

		return $topProperties;
	}
}