<?php

namespace Cesys\CakeEntities\Entities\AmadeusServer\Interfaces;

interface IReservation
{
	/**
	 * Typy enum $paymentCollection
	 */
	public const PaymentCollectionSeller = 'seller';
	public const PaymentCollectionTO = 'tour_operator';

	/**
	 * Typy 'enum' $reservationType. Ve skutečnosti není enum, ale pracuje se s ním tak
	 */
	public const ReservationTypeCustomDirectSell = 'customDirectSell';
	public const ReservationTypeCustomPartnerSell = 'customPartnerSell';
	public const ReservationTypeSystemDirectSell = 'systemDirectSell';
	public const ReservationTypeSystemPartnerSell = 'systemPartnerSell';

	/**
	 * Typy enum $paymentStatus
	 */
	public const PaymentStatusUnpaid = 'unpaid';
	public const PaymentStatusOther = 'other';
	public const PaymentStatusPaidDeposit = 'paid_deposit';
	public const PaymentStatusPaid = 'paid';
	public const PaymentStatusReturned = 'returned';
	public const PaymentStatusCancelled = 'cancelled';
}