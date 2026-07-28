<?php
/**
 * Plugin Name: P1 Email Tuning
 * Description: Keeps P1 operational email useful by suppressing duplicate Sensei enrolment notices.
 * Version: 0.1.0
 * Author: P1 Race Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce's New order email is the operational sale record. Sensei's teacher
 * enrolment email duplicates it and can fire once per course for bundle purchases.
 *
 * Other Sensei teacher notifications remain enabled.
 */
function p1_tune_sensei_teacher_emails(
	bool $should_send,
	string $recipient,
	string $subject,
	string $message,
	string $email_name
): bool {
	unset( $recipient, $message );

	if ( 'student_starts_course' === $email_name ) {
		return false;
	}

	// Compatibility with Sensei's legacy email system.
	if ( 'legacy-email' === $email_name && str_contains( $subject, 'Your student has started a course' ) ) {
		return false;
	}

	return $should_send;
}
add_filter( 'sensei_send_emails', 'p1_tune_sensei_teacher_emails', 20, 5 );
