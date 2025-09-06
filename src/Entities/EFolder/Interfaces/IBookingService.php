<?php

namespace Cesys\CakeEntities\Entities\EFolder\Interfaces;

interface IBookingService
{
	public const TypeInsurance = 'insurance';
	public const TypeParking = 'parking';
	public const TypeOther = 'other';

	public const Types = [
		self::TypeInsurance => self::TypeInsurance,
		self::TypeParking => self::TypeParking,
		self::TypeOther => self::TypeOther,
	];
}