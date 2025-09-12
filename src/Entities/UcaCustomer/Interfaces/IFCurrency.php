<?php

namespace Cesys\CakeEntities\Entities\UcaCustomer\Interfaces;

interface IFCurrency
{
	public const CZK = 1;
	public const EUR = 2;
	public const HUF = 4;
	public const PLN = 7;
	public const USD = 8;
	public const SEK = 9;
	public const TRY = 10;
	public const UAH = 11;
	public const CHF = 12;
	public const GBP = 13;

	public const CodeCZK = 'CZK';
	public const CodeEUR = 'EUR';
	public const CodeHUF = 'HUF';
	public const CodePLN = 'PLN';
	public const CodeUSD = 'USD';
	public const CodeSEK = 'SEK';
	public const CodeTRY = 'TRY';
	public const CodeUAH = 'UAH';
	public const CodeCHF = 'CHF';
	public const CodeGBP = 'GBP';

	public const CodeIds = [
		self::CodeCZK => self::CZK,
		self::CodeEUR => self::EUR,
		self::CodeHUF => self::HUF,
		self::CodePLN => self::PLN,
		self::CodeUSD => self::USD,
		self::CodeSEK => self::SEK,
		self::CodeTRY => self::TRY,
		self::CodeUAH => self::UAH,
		self::CodeCHF => self::CHF,
		self::CodeGBP => self::GBP,
	];


	public const UnitCZK = 'Kč';
	public const UnitEUR = 'Eur';
	public const UnitHUF = 'Ft';
	public const UnitPLN = 'Zł';
	public const UnitUSD = 'US$';
	public const UnitSEK = 'kr';
	public const UnitTRY = 'TL';
	public const UnitUAH = '₴';
	public const UnitCHF = 'CHF';
	public const UnitGBP = '£';


	public const UnitIds = [
		self::UnitCZK => self::CZK,
		self::UnitEUR => self::EUR,
		self::UnitHUF => self::HUF,
		self::UnitPLN => self::PLN,
		self::UnitUSD => self::USD,
		self::UnitSEK => self::SEK,
		self::UnitTRY => self::TRY,
		self::UnitUAH => self::UAH,
		self::UnitCHF => self::CHF,
		self::UnitGBP => self::GBP,
	];

}