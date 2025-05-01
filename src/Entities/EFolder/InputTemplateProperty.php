<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class InputTemplateProperty extends CakeEntity
{
    public int $id;

    public int $inputTemplateId;

	public int $templatePropertyId;

    public int $templatePatternId;

	public ?int $parentId;

    /**
     * Pokud je v databázi určená, hledá se shoda
     * Pokud naopak není, hledá se patternem a bude (po úpravě) doplněna
     * @var string|null
     */
    public ?string $value;

    public ?DateTime $created;

    public ?DateTime $modified;

	public TemplateProperty $templateProperty;

    public TemplatePattern $templatePattern;

	public ?InputTemplateProperty $parent;

	protected bool $hasSearchedValue = false;

	protected array $searchErrors = [];

	protected bool $hasErrors = false;

    public function getName(): string
    {
        return $this->templateProperty->name;
    }

	public function hasSearchedValue(): bool
	{
		return $this->hasSearchedValue;
	}

	/**
	 * @return mixed
	 */
	public function getValue()
	{
		if ($this->value && $this->templateProperty->type === 'array') {
			return unserialize($this->value);
		}
		return $this->value;
	}

	/**
	 * @param array|string|null $texts
	 * @return void
	 */
	public function setValueFrom($texts): void
	{
		if ($this->parent) {
			if ( ! $this->parent->hasSearchedValue()) {
				$this->parent->setValueFrom($texts);
			}
			$texts = $this->parent->getValue();
		}
		$value = $this->findValues($texts);
		if (is_array($value)) {
			$value = serialize($value);
		}
		$this->value = $value;
		$this->hasSearchedValue = true;
	}

	public function hasErrors(): bool
	{
		if ($this->hasErrors) {
			return true;
		}
		$hasErrors = false;
		if ($this->parent) {
			$hasErrors = $this->parent->hasErrors();
		}

		return $hasErrors;
	}

	/**
	 * @param array|string|null $texts
	 * @return
	 */
	public function findValues($texts)
	{
		if ($texts === null) {
			// todo jak error?
			return null;
		}
		if ( ! is_array($texts)) {
			return $this->findValue($texts);
		}
		$values = [];
		foreach ($texts as $text) {
			if (is_array($text)) {
				$values[] = $this->findValues($text);
			} else {
				$values[] = $this->findValue($text);
			}
		}
		return $values;
	}

	public function findValue(string $text)
	{
		preg_match_all($this->templatePattern->pattern, $text, $matches);
		$founds = $matches[1] ?? [];
		$values = array_map(fn($item) => $this->templatePattern->convertValue($item), $founds);

		if ( ! $values) {
			if ($this->templateProperty->required) {
				$this->hasErrors = true;
			}
			return null;
		}

		if ($this->templateProperty->array) {
			return $values;
		} elseif (count($values) > 1) {
			$this->hasErrors = true;
			/*bdump($values);
			bdump($this->templatePattern);
			throw new \Exception('Too many matches.');*/
			return null;
			//todo err;
		}

		$value = $this->value === null ? $values[0] : ($values[0] === $this->value ? $values[0] : null);

		if ($value === null && $this->templateProperty->required) {
			$this->hasErrors = true;
		}

		return $value;
	}

}