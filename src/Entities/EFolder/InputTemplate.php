<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class InputTemplate extends CakeEntity
{
    public int $id;

	public ?int $parentId;

    public string $company;

    public string $type;

    public string $invoiceType;

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
}