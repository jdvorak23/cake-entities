<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
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

	public bool $prohibited;

	public bool $significant;


    public ?DateTime $created;

    public ?DateTime $modified;

	public TemplateProperty $templateProperty;

    public TemplatePattern $templatePattern;

	public ?InputTemplateProperty $parent;


	/**
	 * Neděje se automaticky
	 * @var InputTemplateProperty[]
	 */
	public array $children;

	protected bool $hasSearchedValue = false;

	protected bool $hasError = false;


	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfInputTemplateProperty';
	}


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
		return $this->templateProperty->convertValue($this->value);
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

	public function hasError(): bool
	{
		if ($this->hasError) {
			return true;
		}
		$hasError = false;
		if ($this->parent) {
			$hasError = $this->parent->hasError();
		}

		return $hasError;
	}


	public function hasSignificantError(): bool
	{
		return $this->significant && $this->hasError();
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
				$values[] = $this->findValue($text ?? '');
			}
		}
		return $values;
	}

	public function findValue(string $text)
	{
		preg_match_all($this->templatePattern->pattern, $text, $matches);
//bdump($matches);
		$founds = $matches[1] ?? [];
		$values = array_map(fn($item) => $this->templatePattern->convertValue($item), $founds);
		if ( ! $values) {
			return $this->checkValue(null);
		}

		if ($this->templateProperty->isArray) {
			$checkedValues = [];
			foreach ($values as $value) {
				$checkedValues[] = $this->checkValue($value);
			}
			return $checkedValues;
		} elseif (count($values) > 1) {
			// Property není vedena jako isArray, ale přesto máme více výsledků => výsledek je null a automaticky error
			$this->hasError = true;
			return $this->checkValue(null);
		}

		// Zbývá pole s 1 prvkem
		return $this->checkValue($values[0]);
	}


	private function checkValue(?string $value): ?string
	{
		if ($this->value !== null && $value !== $this->value) {
			// Je nastaveno, že má mít nějakou hodnotu, a tu hodnotu to nemá => null
			$value = null;
		}

		if ($value === null && $this->templateProperty->required) {
			// Získání nějaké hodnoty je povinné a hodnota nezískána => error
			$this->hasError = true;
		}

		if ($this->prohibited) {
			// Je zakázáno získat hodnotu -> buď jakoukoli, nebo tu danou
			if ($this->value === null && $value !== null) {
				$this->hasError = true;
			} elseif ($this->value !== null && $value === $this->value) {
				$this->hasError = true;
			}
		}

		return $value;
	}

}