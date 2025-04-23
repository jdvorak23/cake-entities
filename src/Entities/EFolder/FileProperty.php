<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
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
		if ($this->templateProperty->type === 'array') {
			return unserialize($this->value);
		}
		return $this->value;
	}
}