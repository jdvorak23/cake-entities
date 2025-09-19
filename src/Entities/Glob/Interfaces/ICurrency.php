<?php

namespace Cesys\CakeEntities\Entities\Glob\Interfaces;

use Cesys\CakeEntities\Entities\Interfaces\IBaseCurrency;

interface ICurrency extends IBaseCurrency
{
	public const CZK = 1;
	public const EUR = 2;
	public const HUF = 4;
	public const RSD = 6;
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
	public const CodeRSD = 'RSD';
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
		self::CodeRSD => self::RSD,
		self::CodePLN => self::PLN,
		self::CodeUSD => self::USD,
		self::CodeSEK => self::SEK,
		self::CodeTRY => self::TRY,
		self::CodeUAH => self::UAH,
		self::CodeCHF => self::CHF,
		self::CodeGBP => self::GBP,
	];


	public const UnitCZK = 'Kč';
	public const UnitEUR = '€';
	public const UnitHUF = 'Ft';
	public const UnitRSD = 'дин';
	public const UnitPLN = 'Zł';
	public const UnitUSD = 'US$';
	public const UnitSEK = 'kr';
	public const UnitTRY = '₺';
	public const UnitUAH = '₴';
	public const UnitCHF = 'CHF';
	public const UnitGBP = '£';

	public const UnitIds = [
		self::UnitCZK => self::CZK,
		self::UnitEUR => self::EUR,
		self::UnitHUF => self::HUF,
		self::UnitRSD => self::RSD,
		self::UnitPLN => self::PLN,
		self::UnitUSD => self::USD,
		self::UnitSEK => self::SEK,
		self::UnitTRY => self::TRY,
		self::UnitUAH => self::UAH,
		self::UnitCHF => self::CHF,
		self::UnitGBP => self::GBP,
	];

	public const IdUnits = [
		self::CZK => self::UnitCZK,
		self::EUR => self::UnitEUR,
		self::HUF => self::UnitHUF,
		self::RSD => self::UnitRSD,
		self::PLN => self::UnitPLN,
		self::USD => self::UnitUSD,
		self::SEK => self::UnitSEK,
		self::TRY => self::UnitTRY,
		self::UAH => self::UnitUAH,
		self::CHF => self::UnitCHF,
		self::GBP => self::UnitGBP,
	];

	public const UnitLatinCZK = 'Kč';
	public const UnitLatinEUR = 'Eur';
	public const UnitLatinHUF = 'Ft';
	public const UnitLatinRSD = 'DIN';
	public const UnitLatinPLN = 'Zł';
	public const UnitLatinUSD = 'USD';
	public const UnitLatinSEK = 'kr';
	public const UnitLatinTRY = 'TL';
	public const UnitLatinUAH = 'UAH';
	public const UnitLatinCHF = 'CHF';
	public const UnitLatinGBP = 'GBP';

	public const UnitLatinIds = [
		self::UnitLatinCZK => self::CZK,
		self::UnitLatinEUR => self::EUR,
		self::UnitLatinHUF => self::HUF,
		self::UnitLatinRSD => self::RSD,
		self::UnitLatinPLN => self::PLN,
		self::UnitLatinUSD => self::USD,
		self::UnitLatinSEK => self::SEK,
		self::UnitLatinTRY => self::TRY,
		self::UnitLatinUAH => self::UAH,
		self::UnitLatinCHF => self::CHF,
		self::UnitLatinGBP => self::GBP,
	];

}