<?php
/**
 * LocalWP regression harness for P1 Sensei Woo Bridge.
 *
 * Run with:
 * wp eval-file tests/p1-sensei-woo-bridge-regression.php
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'P1_Sensei_Woo_Bridge' ) || ! function_exists( 'wc_create_order' ) ) {
	throw new RuntimeException( 'WordPress, WooCommerce, and P1 Sensei Woo Bridge must be loaded.' );
}

final class P1_Sensei_Woo_Bridge_Regression {
	private const COURSE_PRODUCT_META = '_course_woocommerce_product';
	private const ORDER_GRANTS_META   = '_p1_sensei_woo_granted_courses';
	private const USER_ACCESS_PREFIX  = '_p1_sensei_woo_access_';

	private int $course_id = 0;
	private int $product_id = 0;
	private int $user_id = 0;
	private array $order_ids = [];

	public function run(): void {
		add_filter( 'woocommerce_email_enabled_new_order', '__return_false', 999 );
		add_filter( 'sensei_send_emails', '__return_false', 999 );

		try {
			$this->create_fixtures();
			$this->test_paid_state_and_on_hold_revocation();
			$this->test_snapshot_revocation_after_mapping_removal();
			$this->test_mapping_reconciliation();
			$this->test_orphan_grant_audit();
			$this->test_daily_audit_schedule();

			echo "PASS: P1 Sensei Woo Bridge entitlement hardening regression suite.\n";
		} finally {
			$this->cleanup();
		}
	}

	private function create_fixtures(): void {
		$suffix = strtolower( wp_generate_password( 8, false, false ) );

		$this->user_id = wp_create_user(
			'p1_bridge_test_' . $suffix,
			wp_generate_password( 24, true, true ),
			'p1-bridge-test-' . $suffix . '@example.test'
		);
		$this->assert( ! is_wp_error( $this->user_id ), 'Temporary user was created.' );

		$this->course_id = wp_insert_post(
			[
				'post_type'   => 'course',
				'post_status' => 'publish',
				'post_title'  => 'P1 Bridge Regression ' . $suffix,
			],
			true
		);
		$this->assert( ! is_wp_error( $this->course_id ), 'Temporary course was created.' );

		$product = new WC_Product_Simple();
		$product->set_name( 'P1 Bridge Regression ' . $suffix );
		$product->set_status( 'publish' );
		$product->set_regular_price( '10.00' );
		$this->product_id = $product->save();
		$this->assert( $this->product_id > 0, 'Temporary product was created.' );

		add_post_meta( $this->course_id, self::COURSE_PRODUCT_META, (string) $this->product_id );
	}

	private function test_paid_state_and_on_hold_revocation(): void {
		$order = $this->create_order();

		do_action( 'woocommerce_payment_complete', $order->get_id() );
		$this->assert( ! $this->has_grant(), 'Pending order cannot grant course access.' );

		$order->update_status( 'processing' );
		$this->assert( $this->has_grant(), 'Paid processing order grants course access.' );
		$this->assert( in_array( $this->course_id, $this->snapshot( $order->get_id() ), true ), 'Paid order stores a course-grant snapshot.' );

		$order->update_status( 'on-hold' );
		$this->assert( ! $this->has_grant(), 'On-hold order revokes course access.' );
		$this->assert( ! $this->snapshot( $order->get_id() ), 'On-hold revocation clears the order snapshot.' );
	}

	private function test_snapshot_revocation_after_mapping_removal(): void {
		$order = $this->create_order();
		$order->update_status( 'processing' );
		$this->assert( $this->has_grant(), 'Snapshot test order granted access.' );

		delete_post_meta( $this->course_id, self::COURSE_PRODUCT_META );
		$this->assert( in_array( $this->course_id, $this->snapshot( $order->get_id() ), true ), 'Stored snapshot survives a mapping removal until reconciliation or reversal.' );

		$order->update_status( 'refunded' );
		$this->assert( ! $this->has_grant(), 'Refund revokes from the stored snapshot after mapping removal.' );

		add_post_meta( $this->course_id, self::COURSE_PRODUCT_META, (string) $this->product_id );
	}

	private function test_mapping_reconciliation(): void {
		$order = $this->create_order();
		$order->update_status( 'processing' );
		$this->assert( $this->has_grant(), 'Mapping reconciliation order granted access.' );

		delete_post_meta( $this->course_id, self::COURSE_PRODUCT_META );
		$removed = P1_Sensei_Woo_Bridge::reconcile_course_access( $this->course_id );
		$this->assert( ! $this->has_grant() && $removed['revoked'] >= 1, 'Removing a mapping reconciles and revokes old access.' );

		add_post_meta( $this->course_id, self::COURSE_PRODUCT_META, (string) $this->product_id );
		$added = P1_Sensei_Woo_Bridge::reconcile_course_access( $this->course_id );
		$this->assert( $this->has_grant() && $added['granted'] >= 1, 'Adding a mapping reconciles and grants existing paid buyers.' );
		$this->assert( in_array( $this->course_id, $this->snapshot( $order->get_id() ), true ), 'Mapping reconciliation backfills the paid order snapshot.' );

		$order->update_status( 'on-hold' );
	}

	private function test_orphan_grant_audit(): void {
		update_user_meta(
			$this->user_id,
			self::USER_ACCESS_PREFIX . $this->course_id,
			[
				'order_id'   => 0,
				'product_id' => $this->product_id,
				'granted_at' => current_time( 'mysql' ),
			]
		);

		$read_only = P1_Sensei_Woo_Bridge::audit_grants( false, $this->course_id );
		$this->assert( $read_only['orphaned'] >= 1 && $this->has_grant(), 'Read-only audit detects but does not remove an orphaned grant.' );

		$fixed = P1_Sensei_Woo_Bridge::audit_grants( true, $this->course_id );
		$this->assert( $fixed['revoked'] >= 1 && ! $this->has_grant(), 'Repair audit revokes an orphaned grant.' );
	}

	private function test_daily_audit_schedule(): void {
		P1_Sensei_Woo_Bridge::schedule_daily_audit();
		$this->assert( false !== wp_next_scheduled( 'p1_sensei_woo_bridge_daily_audit' ), 'Daily orphan-grant audit is scheduled.' );
	}

	private function create_order(): WC_Order {
		$order = wc_create_order( [ 'customer_id' => $this->user_id ] );
		$this->assert( ! is_wp_error( $order ), 'Temporary order was created.' );

		$product = wc_get_product( $this->product_id );
		$order->add_product( $product, 1 );
		$order->calculate_totals();
		$order->save();
		$this->order_ids[] = $order->get_id();

		return $order;
	}

	private function has_grant(): bool {
		return ! empty( get_user_meta( $this->user_id, self::USER_ACCESS_PREFIX . $this->course_id, true ) );
	}

	private function snapshot( int $order_id ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return [];
		}

		$order->read_meta_data( true );
		return array_values( array_filter( array_map( 'absint', (array) $order->get_meta( self::ORDER_GRANTS_META, true ) ) ) );
	}

	private function assert( bool $condition, string $message ): void {
		if ( ! $condition ) {
			throw new RuntimeException( 'FAIL: ' . $message );
		}

		echo 'PASS: ' . $message . "\n";
	}

	private function cleanup(): void {
		wp_clear_scheduled_hook( 'p1_sensei_woo_bridge_reconcile_course', [ $this->course_id ] );

		foreach ( $this->order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->delete( true );
			}
		}

		if ( $this->product_id ) {
			$product = wc_get_product( $this->product_id );
			if ( $product ) {
				$product->delete( true );
			}
		}

		if ( $this->course_id ) {
			wp_delete_post( $this->course_id, true );
		}

		if ( $this->user_id ) {
			wp_delete_user( $this->user_id );
		}
	}
}

( new P1_Sensei_Woo_Bridge_Regression() )->run();
