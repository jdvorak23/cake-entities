<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\CakeEntity;
use Nette\Utils\DateTime;
use Nette\Utils\FileSystem;

class File extends CakeEntity
{
    public int $id;

	public int $folderId;

	public ?int $inputTemplateId;

    public string $filename;

	public ?string $extension;

	public ?string $mime;

	public int $size;

	public bool $active;

    public ?DateTime $created;
    public ?DateTime $modified;

    public ?int $createdBy;
    public ?int $modifiedBy;

	/**
	 * @var FileProperty[] file_id
	 */
	public array $fileProperties;

    public string $path;

	public static function getModelClass(): string
	{
		return 'EfFile';
	}

    public static function getExcludedFromProperties(): array
    {
        return ['path'];
    }


    public function getFullFilename(): string
	{
		$filename = $this->filename;
		if (isset($this->extension) && $this->extension !== '') {
			$filename .= '.' . $this->extension;
		}

		return $filename;
	}

    public function getFullPath(): string
    {
        if ( ! isset($this->path) ) {
            throw new \LogicException('Path is not set');
        }
        if ( ! isset($this->folderId) ) {
            throw new \LogicException('FolderId is not set');
        }
        return Filesystem::joinPaths($this->path, $this->folderId, $this->getFullFilename());
    }
}