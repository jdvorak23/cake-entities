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

	public const CustomerCountries = [
		self::CustomerCountryCz => self::CustomerCountryCz,
		self::CustomerCountrySk => self::CustomerCountrySk,
		self::CustomerCountryHu => self::CustomerCountryHu,
		self::CustomerCountryPl => self::CustomerCountryPl,
	];

	/**
	 * Typy enum $reservationStatus
	 */
	public const ReservationStatusCreated = 'created';
	/** @deprecated */
	public const ReservationStatusInProgress = 'in_progress';
	public const ReservationStatusOption = 'option';
	public const ReservationStatusBooking = 'booking';
	public const ReservationStatusCheckedOut = 'checked_out';
	/** @deprecated */
	public const ReservationStatusClosed = 'closed';
	public const ReservationStatusCancelledClient = 'cancelled_client';
	public const ReservationStatusCancelledTourOperator = 'cancelled_tour_operator';

	public const ReservationStatuses = [
		self::ReservationStatusCreated => self::ReservationStatusCreated,
		self::ReservationStatusInProgress => self::ReservationStatusInProgress,
		self::ReservationStatusOption => self::ReservationStatusOption,
		self::ReservationStatusBooking => self::ReservationStatusBooking,
		self::ReservationStatusCheckedOut => self::ReservationStatusCheckedOut,
		self::ReservationStatusClosed => self::ReservationStatusClosed,
		self::ReservationStatusCancelledClient => self::ReservationStatusCancelledClient,
		self::ReservationStatusCancelledTourOperator => self::ReservationStatusCancelledTourOperator,
	];

	/**
	 * Typy enum $paymentStatus
	 */
	public const PaymentStatusUnpaid = 'unpaid';
	/** @deprecated */
	public const PaymentStatusOther = 'other';
	public const PaymentStatusPaidDeposit = 'paid_deposit';
	public const PaymentStatusPaid = 'paid';
	public const PaymentStatusReturned = 'returned';
	public const PaymentStatusCancelled = 'cancelled';

	public const PaymentStatuses = [
		self::PaymentStatusUnpaid => self::PaymentStatusUnpaid,
		self::PaymentStatusOther => self::PaymentStatusOther,
		self::PaymentStatusPaidDeposit => self::PaymentStatusPaidDeposit,
		self::PaymentStatusPaid => self::PaymentStatusPaid,
		self::PaymentStatusReturned => self::PaymentStatusReturned,
		self::PaymentStatusCancelled => self::PaymentStatusCancelled,
	];

	/**
	 * Typy 'enum' $reservationType. Ve skutečnosti není enum, ale pracuje se s ním tak
	 */
	public const ReservationTypeCustomDirectSell = 'customDirectSell';
	public const ReservationTypeCustomPartnerSell = 'customPartnerSell';
	public const ReservationTypeSystemDirectSell = 'systemDirectSell';
	public const ReservationTypeSystemPartnerSell = 'systemPartnerSell';
	public const ReservationTypeNoDeltaSell = 'noDeltaSell';

	/**
	 * Typy enum $paymentCollection
	 */
	public const PaymentCollectionSeller = 'seller';
	public const PaymentCollectionTO = 'tour_operator';

}