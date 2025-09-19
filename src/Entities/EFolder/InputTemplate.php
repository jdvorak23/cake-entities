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

	protected bool $parsedInvoiceError = false;


	public static function getModelClass(): string
	{
		return 'EfInputTemplate';
	}

	/**
	 * @return InputTemplateProperty[]
	 */
    public function getInputTemplateProperties(bool $sorted = true): array
    {
		$properties = $this->properties;
		if (isset($this->parent)) {
			$properties = $properties + $this->parent->getInputTemplateProperties(false);
		}
		if ( ! $sorted) {
			return $properties;
		}
		// Na začátku chceme mít significant properties a pak ty co mají hodnotu, až poté ty bez hodnoty
		// Tj. performance, aby se co nejrychleji vyloučily ty, co nechceme
		$significant = [];
		$withValue = [];
		$rest = [];
		foreach ($properties as $property) {
			if ($property->significant) {
				$significant[$property->id] = $property;
			} elseif ($property->value !== null) {
				$withValue[$property->id] = $property;
			} else {
				$rest[$property->id] = $property;
			}
		}
		return $significant + $withValue + $rest;
    }


	public function getSignificantInputTemplateProperties(): array
	{
		$properties = [];
		foreach ($this->getInputTemplateProperties() as $property) {
			if ( ! $property->significant) {
				break;
			}
			$properties[$property->id] = $property;
		}
		return $properties;
	}


	public function getWithValueInputTemplateProperties(): array
	{
		$properties = [];
		foreach ($this->getInputTemplateProperties() as $property) {
			if ( ! $property->significant && ! $property->value) {
				break;
			}
			if ( ! $property->significant && $property->value) {
				$properties[$property->id] = $property;
			}
		}

		return $properties;
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
		foreach ($this->getInputTemplateProperties(false) as $inputTemplateProperty) {
			if ($inputTemplateProperty->hasError()) {
				$errorsCount++;
			}
		}
		return $errorsCount;
	}

	public function hasSignificantError(): bool
	{
		foreach ($this->getSignificantInputTemplateProperties() as $inputTemplateProperty) {
			if ($inputTemplateProperty->hasSignificantError()) {
				return true;
			}
		}
		return false;
	}

	public function setParsedInvoiceError(): void
	{
		$this->parsedInvoiceError = true;
	}

	public function hasParsedInvoiceError(): bool
	{
		return $this->parsedInvoiceError;
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
		$significant = [];
		$withValue = [];
		$rest = [];
		foreach ($this->properties as $property) {
			if ($property->significant) {
				$significant[$property->id] = $property;
			} elseif ($property->value !== null) {
				$withValue[$property->id] = $property;
			} else {
				$rest[$property->id] = $property;
			}
			$property->children = [];
		}
		$allProperties = $significant + $withValue + $rest;

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