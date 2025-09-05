<?php

namespace Cesys\CakeEntities\Entities\EFolder\Interfaces;

interface IMoneyTransaction
{
	/**
	 * Typy enum 'method'
	 */
	public const MethodTransfer = 'transfer'; // Platba na účet
	public const MethodCard = 'card'; // Platba kartou
	public const MethodCash = 'cash'; // Cash - pokladna
	public const MethodVoucher = 'voucher'; // Poukázka nebo podobné
	public const MethodRound = 'round';

	public const Methods = [
		self::MethodTransfer => self::MethodTransfer,
		self::MethodCard => self::MethodCard,
		self::MethodCash => self::MethodCash,
		self::MethodVoucher => self::MethodVoucher,
		self::MethodRound => self::MethodRound,
	];

	/**
	 * U výdajů 'voucher' a 'round' nemají smysl, ty jsou jen pro příjmy
	 */
	public const ExpenseMethods = [
		self::MethodTransfer => self::MethodTransfer,
		self::MethodCard => self::MethodCard,
		self::MethodCash => self::MethodCash,
	];


	/**
	 * Typy enum 'type'
	 */
	public const TypePayment = 'payment'; // Jakákoli platba za něco, kromě níže uvedených typů
	public const TypeCommission = 'commission'; // Platba za komisi (zatím pouze partnerské TA, tj. provize back u payment_collection = tour_operator)
	public const TypeService = 'service'; // Platba za službu (dodavateli)

}