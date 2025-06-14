<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Nette\Utils\DateTime;

class FileProperty extends CakeEntity
{
    public int $id;

	public int $fileId;

	public int $templatePropertyId;

	public ?string $value;
	
    public ?DateTime $created;
    public ?DateTime $modified;

	public TemplateProperty $templateProperty;

	/**
	 * @return mixed
	 */
	public function getValue()
	{
		return $this->templateProperty->convertValue($this->value);
	}
}