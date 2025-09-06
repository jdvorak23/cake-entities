<?php

namespace Cesys\CakeEntities\Entities\AmadeusServer\Interfaces;

interface IReservation
{
	/**
	 * Typy enum $customerCountry
	 */
	public const CustomerCountryCz = 'cz';
	public const CustomerCountrySk = 'sk';
	public const CustomerCountryHu = 'hu';
	public const CustomerCountryPl = 'pl';

	/**
	 * Typy enum $reservationStatus
	 */
	public const ReservationStatusCreated = 'created';
	public const ReservationStatusInProgress = 'in_progress';
	public const ReservationStatusOption = 'option';
	public const ReservationStatusBooking = 'booking';
	public const ReservationStatusCheckedOut = 'checked_out';
	public const ReservationStatusClosed = 'closed';
	public const ReservationStatusCancelledClient = 'cancelled_client';
	public const ReservationStatusCancelledTourOperator = 'cancelled_tour_operator';

	/**
	 * Typy enum $paymentStatus
	 */
	public const PaymentStatusUnpaid = 'unpaid';
	public const PaymentStatusOther = 'other';
	public const PaymentStatusPaidDeposit = 'paid_deposit';
	public const PaymentStatusPaid = 'paid';
	public const PaymentStatusReturned = 'returned';
	public const PaymentStatusCancelled = 'cancelled';

	/**
	 * Typy 'enum' $reservationType. Ve skutečnosti není enum, ale pracuje se s ním tak
	 */
	public const ReservationTypeCustomDirectSell = 'customDirectSell';
	public const ReservationTypeCustomPartnerSell = 'customPartnerSell';
	public const ReservationTypeSystemDirectSell = 'systemDirectSell';
	public const ReservationTypeSystemPartnerSell = 'systemPartnerSell';

	/**
	 * Typy enum $paymentCollection
	 */
	public const PaymentCollectionSeller = 'seller';
	public const PaymentCollectionTO = 'tour_operator';

}