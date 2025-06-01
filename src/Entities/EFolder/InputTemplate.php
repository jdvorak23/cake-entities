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

	public ?int $fSubjectId; // todo podmínka do výběru templat na parsování

	public bool $active;

    public ?DateTime $created;

    public ?DateTime $modified;

	public ?InputTemplate $parent;

    /**
     * @var InputTemplateProperty[] input_template_id
     */
    public array $properties;

    public function getInputTemplateProperties(): array
    {
		$result = $this->properties;
		if (isset($this->parent)) {
			$result = $this->properties + $this->parent->getInputTemplateProperties();
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
				if ($inputTemplateProperty->hasError() && $interruptOnError) {
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
}