<?php

namespace Cesys\CakeEntities\Entities\EFolder\Interfaces;

interface IFile
{
	public const LabelContract = 'contract';
	public const LabelParsedInvoice = 'parsed_invoice';
	public const LabelInvoice = 'invoice';

	public const LabelSignedContract = 'signed_contract';

	public const Labels = [
		self::LabelContract,
		self::LabelParsedInvoice,
		self::LabelInvoice,
		self::LabelSignedContract,
	];
	public const AutomaticLabels = [
		self::LabelContract,
		self::LabelParsedInvoice,
		self::LabelInvoice,
	];
}