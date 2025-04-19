<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;

class File extends CakeEntity
{
    public int $id;

	public int $folderId;

	public ?int $inputTemplateId;

    public string $filename;

	public string $extension;

	public string $mime;

	public int $size;

	public bool $active;

    public ?DateTime $created;
    public ?DateTime $modified;

	/**
	 * @var FileProperty[] file_id
	 */
	public array $fileProperties;

	public static function getModelClass(): string
	{
		return 'EfFile';
	}

	public function getFullFilename(): string
	{
		$filename = $this->filename;
		if (isset($this->extension) && $this->extension !== '') {
			$filename .= '.' . $this->extension;
		}

		return $filename;
	}
}