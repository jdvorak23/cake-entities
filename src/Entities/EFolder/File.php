<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Model\Entities\CakeEntity;
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

	public ?InputTemplate $inputTemplate;

	/**
	 * @var Invoice[] file_id
	 */
	public array $invoices;

	public Folder $folder;

    public string $path;

	/**
	 * @var callable
	 */
	protected $parsedInvoiceFactory;

	protected $parsedInvoice;

	/**
	 * nechci závislost ach jko
	 * @var callable
	 *
	protected $documentInvoiceFactory;
	public function setDocumentInvoiceFactory(callable $documentInvoiceFactory)
	{
		$this->documentInvoiceFactory = $documentInvoiceFactory;
	}*/

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

	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfFile';
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

	public function hasInvoiceOfType(int $fInvoiceTypeId): bool
	{
		foreach ($this->invoices as $invoice) {
			if ($invoice->fInvoice->fInvoiceTypeId === $fInvoiceTypeId) {
				return true;
			}
		}
		return false;
	}

}