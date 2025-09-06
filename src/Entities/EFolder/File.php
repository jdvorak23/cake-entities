<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\EFolder\Interfaces\IFile;
use Cesys\CakeEntities\Model\Entities\CakeEntity;
use Cesys\Utils\Entities\FilePathInfo;
use Cesys\Utils\FileSystemHelper;
use Nette\Utils\DateTime;
use Nette\Utils\FileSystem;

class File extends CakeEntity implements IFile
{
    public int $id;

	public ?int $folderId;

	public ?int $inputTemplateId;

	public ?int $reservationId;

	public ?string $label;

	public string $dir;

    public string $filename;

	public ?string $extension;

	public ?string $mime;

	public int $size;

	public ?string $hash;

	public bool $active;

    public ?DateTime $created;
    public ?DateTime $modified;

    public ?int $createdBy;
    public ?int $modifiedBy;

	/**
	 * @var FileProperty[] file_id
	 */
	public array $fileProperties;

	public ?InputTemplate $inputTemplate;

	public ?Folder $folder;

	/**
	 * @var ?FileInvoice id fileId
	 */
	public ?FileInvoice $fileInvoice;


    public string $path;

	/**
	 * @var callable
	 */
	protected $parsedInvoiceFactory;

	protected $parsedInvoice;


	public static function getModelClass(): string
	{
		return 'EfFile';
	}

	public static function getExcludedFromProperties(): array
	{
		return ['path'];
	}

	public function setParsedInvoiceFactory(callable $parsedInvoiceFactory)
	{
		$this->parsedInvoiceFactory = $parsedInvoiceFactory;
	}

	/**
	 * @return ?\Cesys\EFolder\Documents\ParsedInvoice
	 */
	public function getParsedInvoice()
	{
		return $this->parsedInvoice ??= ($this->parsedInvoiceFactory)($this);
	}

    public function getFullFilename(): string
	{
		$filename = $this->filename;
		if (isset($this->extension) && $this->extension !== '') {
			$filename .= '.' . $this->extension;
		}

		return $filename;
	}

	public function getFullDir(): string
	{
		if ( ! isset($this->path) ) {
			throw new \LogicException('Path is not set');
		}
		return Filesystem::joinPaths($this->path, $this->dir);
	}

    public function getFullPath(): string
    {
        if ( ! isset($this->path) ) {
            throw new \LogicException('Path is not set');
        }

        return Filesystem::joinPaths($this->getFullDir(), $this->getFullFilename());
    }

	public function getAttachedFilePathInfo(): FilePathInfo
	{
		$filePathInfo = FilePathInfo::createFromPath($this->getFullPath());
		$filePathInfo->onChange[] = [$this, 'appendFromFilePathInfo'];
		return $filePathInfo;
	}

	public function appendFromFilePathInfo(FilePathInfo $filePathInfo, bool $attach = false): void
	{
		$this->filename = $filePathInfo->getFilename();
		$this->extension = $filePathInfo->getExtension();
		// todo ověřit
		$this->dir = FileSystemHelper::getRelativePath($this->path, $filePathInfo->getDirname());
		if ($attach) {
			$filePathInfo->onChange[] = [$this, 'appendFromFilePathInfo'];
		}
	}

}