<?php

namespace Cesys\CakeEntities\Entities\EFolder;

use Cesys\CakeEntities\Entities\UcaCustomer\EFolder\FSubject;
use Cesys\CakeEntities\Model\Entities\CakeEntity;

/**
 * process_number = vorgang, vorgangsnummer, buchungsnummer
 */
class ProcessNumber extends CakeEntity
{
	public int $id;

	public int $folderId;

	public int $fSubjectId;

	public string $number;

	public Folder $folder;

	public FSubject $fSubject;

	/**
	 * @var FileInvoice[] processNumberId
	 */
	public array $fileInvoices;


	public static function getModelClass(): string
	{
		return static::$modelClasses[static::class] ??= 'EfProcessNumber';
	}
}