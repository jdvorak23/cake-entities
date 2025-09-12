<?php

namespace Cesys\CakeEntities\Entities\Glob\Interfaces;

interface ICurrency
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

}