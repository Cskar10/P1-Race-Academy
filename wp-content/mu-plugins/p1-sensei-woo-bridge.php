<?php
/**
 * Plugin Name: P1 Sensei Woo Bridge
 * Description: Local bridge that gates product-linked Sensei courses behind WooCommerce purchases. Supports multiple products per course (e.g. single-course and full-access bundle tiers).
 * Version: 0.4.0
 * Author: P1 Race Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class P1_Sensei_Woo_Bridge {
	private const COURSE_PRODUCT_META = '_course_woocommerce_product';
	private const USER_ACCESS_PREFIX  = '_p1_sensei_woo_access_';
	private const ORDER_GRANTS_META   = '_p1_sensei_woo_granted_courses';
	private const REQUIRED_QUERY_ARG  = 'p1_course_access_required';
	private const DAILY_AUDIT_HOOK    = 'p1_sensei_woo_bridge_daily_audit';
	private const RECONCILE_HOOK      = 'p1_sensei_woo_bridge_reconcile_course';
	private const LAST_AUDIT_OPTION   = 'p1_sensei_woo_bridge_last_audit';

	public static function init(): void {
		add_filter( 'sensei_can_user_manually_enrol', [ __CLASS__, 'filter_manual_enrolment' ], 20, 2 );
		add_filter( 'sensei_can_access_course_content', [ __CLASS__, 'filter_course_content_access' ], 20, 4 );
		add_filter( 'sensei_can_user_view_lesson', [ __CLASS__, 'filter_lesson_access' ], 20, 4 );
		add_filter( 'render_block', [ __CLASS__, 'hide_locked_course_action_blocks' ], 30, 2 );
		add_filter( 'the_content', [ __CLASS__, 'append_course_purchase_cta' ], 20 );

		add_action( 'template_redirect', [ __CLASS__, 'redirect_blocked_sensei_content' ], 5 );
		add_action( 'woocommerce_payment_complete', [ __CLASS__, 'grant_order_access' ] );
		add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'grant_order_access' ] );
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'grant_order_access' ] );
		add_action( 'woocommerce_order_status_changed', [ __CLASS__, 'grant_order_access_on_status_change' ], 10, 4 );
		add_action( 'woocommerce_after_checkout_validation', [ __CLASS__, 'validate_checkout_account' ], 10, 2 );

		// Revoke course access when a paid order is reversed.
		add_action( 'woocommerce_order_status_refunded', [ __CLASS__, 'revoke_order_access' ], 10, 2 );
		add_action( 'woocommerce_order_status_cancelled', [ __CLASS__, 'revoke_order_access' ], 10, 2 );
		add_action( 'woocommerce_order_status_failed', [ __CLASS__, 'revoke_order_access' ], 10, 2 );
		add_action( 'woocommerce_order_status_on-hold', [ __CLASS__, 'revoke_order_access' ], 10, 2 );

		// Partial refunds keep the order paid; flag for manual review instead of auto-revoking.
		add_action( 'woocommerce_order_partially_refunded', [ __CLASS__, 'flag_partial_refund_for_review' ], 10, 2 );

		add_action( 'added_post_meta', [ __CLASS__, 'queue_course_reconciliation' ], 10, 4 );
		add_action( 'updated_post_meta', [ __CLASS__, 'queue_course_reconciliation' ], 10, 4 );
		add_action( 'deleted_post_meta', [ __CLASS__, 'queue_course_reconciliation' ], 10, 4 );
		add_action( self::RECONCILE_HOOK, [ __CLASS__, 'reconcile_course_access' ] );
		add_action( self::DAILY_AUDIT_HOOK, [ __CLASS__, 'run_scheduled_audit' ] );
		add_action( 'init', [ __CLASS__, 'schedule_daily_audit' ] );
	}

	public static function filter_manual_enrolment( bool $can_enrol, int $course_id ): bool {
		if ( ! self::is_product_linked_course( $course_id ) ) {
			return $can_enrol;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id || ! self::user_has_course_access( $user_id, $course_id ) ) {
			return false;
		}

		return $can_enrol;
	}

	public static function filter_course_content_access( bool $can_access, int $course_id, ?int $user_id, string $context ): bool {
		if ( ! self::is_product_linked_course( $course_id ) ) {
			return $can_access;
		}

		if ( self::user_has_course_access( (int) $user_id, $course_id ) ) {
			return true;
		}

		return false;
	}

	public static function filter_lesson_access( bool $can_view, int $lesson_id, int $user_id, array $checks ): bool {
		$course_id = self::get_course_id_for_sensei_content( $lesson_id );
		if ( ! self::is_product_linked_course( $course_id ) ) {
			return $can_view;
		}

		if ( ! self::user_has_course_access( $user_id, $course_id ) ) {
			return false;
		}

		return $can_view;
	}

	public static function hide_locked_course_action_blocks( string $block_content, array $block ): string {
		$block_name = $block['blockName'] ?? '';
		if ( ! in_array( $block_name, [ 'sensei-lms/course-actions', 'sensei-lms/button-take-course' ], true ) ) {
			return $block_content;
		}

		$course_id = self::get_current_course_id();
		if ( ! self::is_product_linked_course( $course_id ) || self::user_has_course_access( get_current_user_id(), $course_id ) ) {
			return $block_content;
		}

		return '';
	}

	public static function append_course_purchase_cta( string $content ): string {
		if ( ! is_singular( 'course' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$course_id = get_the_ID();
		if ( ! self::is_product_linked_course( $course_id ) || self::user_has_course_access( get_current_user_id(), $course_id ) ) {
			return $content;
		}

		$product_ids = self::get_product_ids_for_course( $course_id );
		if ( ! $product_ids || ! function_exists( 'wc_get_product' ) ) {
			return $content;
		}

		$primary_product = wc_get_product( $product_ids[0] );
		if ( ! $primary_product ) {
			return $content;
		}

		$message = isset( $_GET[ self::REQUIRED_QUERY_ARG ] )
			? '<p class="p1-sensei-woo-bridge__notice">Buy access to continue with this course.</p>'
			: '';

		$cta  = '<section class="p1-sensei-woo-bridge" style="border:1px solid #d8dee4;padding:24px;margin:32px 0;background:#f7fafc;">';
		$cta .= $message;
		$cta .= '<h2 style="margin-top:0;">Buy ' . esc_html( get_the_title( $course_id ) ) . '</h2>';
		$cta .= self::render_product_option( $primary_product, true );

		// Any further linked products are bundles/full-access tiers that also unlock this course.
		foreach ( array_slice( $product_ids, 1 ) as $extra_id ) {
			$extra_product = wc_get_product( $extra_id );
			if ( $extra_product ) {
				$cta .= self::render_product_option( $extra_product, false );
			}
		}

		$cta .= '</section>';

		return $content . $cta;
	}

	private static function render_product_option( WC_Product $product, bool $is_primary ): string {
		$product_id  = $product->get_id();
		$price_html  = $product->get_price_html();
		$product_url = get_permalink( $product_id );
		$buy_url     = class_exists( 'P1_Checkout_Flow' )
			? P1_Checkout_Flow::get_buy_now_url( $product_id )
			: add_query_arg( 'add-to-cart', $product_id, home_url( '/' ) );

		$label = $is_primary
			? esc_html( $product->get_name() )
			: 'Or get ' . esc_html( $product->get_name() );

		$html  = '<div class="p1-sensei-woo-bridge__option" style="margin:16px 0;">';
		$html .= '<p style="margin:0 0 8px;"><strong>' . $label . '</strong>';
		$html .= $price_html ? ' — ' . wp_kses_post( $price_html ) : '';
		$html .= '</p>';
		$html .= '<p style="margin:0;"><a class="button wp-element-button" href="' . esc_url( $buy_url ) . '">Buy now</a> ';
		$html .= '<a href="' . esc_url( $product_url ) . '">View details</a></p>';
		$html .= '</div>';

		return $html;
	}

	public static function redirect_blocked_sensei_content(): void {
		if ( ! is_singular( [ 'lesson', 'quiz' ] ) ) {
			return;
		}

		$content_id = get_queried_object_id();
		$course_id  = self::get_course_id_for_sensei_content( $content_id );
		if ( ! self::is_product_linked_course( $course_id ) || self::user_has_course_access( get_current_user_id(), $course_id ) ) {
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				self::REQUIRED_QUERY_ARG,
				'1',
				get_permalink( $course_id )
			)
		);
		exit;
	}

	public static function grant_order_access_on_status_change( int $order_id, string $old_status, string $new_status, $order ): void {
		if ( in_array( $new_status, [ 'processing', 'completed' ], true ) ) {
			self::grant_order_access( $order_id );
		}
	}

	public static function grant_order_access( int $order_id ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order || ! $order->is_paid() ) {
			return;
		}

		$user_id = self::get_user_id_for_order( $order );
		if ( ! $user_id ) {
			$order->add_order_note( 'P1 Sensei Woo Bridge: no WordPress user found for this order, so course access was not granted.' );
			return;
		}

		$granted_courses = (array) $order->get_meta( self::ORDER_GRANTS_META, true );
		$granted_courses = array_map( 'absint', $granted_courses );
		$granted_courses = array_filter( $granted_courses );

		foreach ( self::get_course_ids_for_order( $order ) as $course_id ) {
			$has_bridge_grant = self::bridge_user_has_grant( $user_id, $course_id );
			if ( $has_bridge_grant ) {
				$granted_courses[] = $course_id;
				continue;
			}

			if ( self::grant_user_course_access( $user_id, $course_id, $order ) ) {
				$granted_courses[] = $course_id;
				$order->add_order_note(
					sprintf(
						'P1 Sensei Woo Bridge: granted user #%d access to course #%d.',
						$user_id,
						$course_id
					)
				);
			}
		}

		$order->update_meta_data( self::ORDER_GRANTS_META, array_values( array_unique( $granted_courses ) ) );
		$order->save();
	}

	/**
	 * Revoke course access for orders that are no longer considered paid.
	 *
	 * Full refunds move the order to the `refunded` status (handled here); partial
	 * refunds keep the order paid and are routed to manual review instead.
	 */
	public static function revoke_order_access( int $order_id, $order = null ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// Status hooks can pass an order object whose in-memory meta predates the grant.
		$order->read_meta_data( true );
		$user_id = self::get_user_id_for_order( $order );
		if ( ! $user_id ) {
			return;
		}

		$revoked    = [];
		$course_ids = self::get_order_granted_course_ids( $order );

		// Legacy orders may predate the stored snapshot, so retain a one-time fallback.
		if ( ! $course_ids ) {
			$course_ids = self::get_course_ids_for_order( $order );
		}

		foreach ( $course_ids as $course_id ) {
			// Keep access if another still-paid order entitles this user to the course.
			if ( self::user_entitled_by_other_order( $user_id, $course_id, $order_id ) ) {
				$order->add_order_note(
					sprintf(
						'P1 Sensei Woo Bridge: kept user #%d access to course #%d after status "%s" because another paid order still covers it.',
						$user_id,
						$course_id,
						$order->get_status()
					)
				);
				continue;
			}

			if ( self::revoke_user_course_access( $user_id, $course_id ) ) {
				$revoked[] = $course_id;
				$order->add_order_note(
					sprintf(
						'P1 Sensei Woo Bridge: revoked user #%d access to course #%d after order status "%s".',
						$user_id,
						$course_id,
						$order->get_status()
					)
				);
			}
		}

		// Clear this order's grant record so a later return to a paid status re-grants cleanly.
		$order->delete_meta_data( self::ORDER_GRANTS_META );
		$order->save();
	}

	/**
	 * Add a manual-review note for partial refunds. Access is intentionally NOT revoked
	 * automatically because a partial refund keeps the order in a paid status.
	 */
	public static function flag_partial_refund_for_review( int $order_id, int $refund_id ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$course_ids = self::get_course_ids_for_order( $order );
		if ( ! $course_ids ) {
			return;
		}

		$order->add_order_note(
			sprintf(
				'P1 Sensei Woo Bridge: partial refund #%d detected for course(s) #%s. Access was NOT auto-revoked — manual review required per P1 refund policy.',
				$refund_id,
				implode( ', ', $course_ids )
			)
		);
		$order->save();
	}

	private static function revoke_user_course_access( int $user_id, int $course_id ): bool {
		delete_user_meta( $user_id, self::USER_ACCESS_PREFIX . $course_id );

		if ( class_exists( 'Sensei_Course_Enrolment' ) ) {
			try {
				Sensei_Course_Enrolment::get_course_instance( $course_id )->withdraw( $user_id );
			} catch ( Throwable $error ) {
				// Fall through; the user meta removal above already closes the bridge grant.
			}
		}

		return ! self::bridge_user_has_grant( $user_id, $course_id );
	}

	/**
	 * Whether the user still has a different paid order that covers this course's
	 * product, excluding the order currently being reversed.
	 */
	private static function user_entitled_by_other_order( int $user_id, int $course_id, int $excluded_order_id ): bool {
		return self::user_has_paid_order_for_course( $user_id, $course_id, $excluded_order_id );
	}

	private static function user_has_paid_order_for_course( int $user_id, int $course_id, int $excluded_order_id = 0 ): bool {
		$product_ids = self::get_product_ids_for_course( $course_id );
		if ( ! $product_ids || ! function_exists( 'wc_get_orders' ) ) {
			return false;
		}

		foreach ( self::get_paid_order_ids_for_user( $user_id ) as $other_order_id ) {
			if ( (int) $other_order_id === $excluded_order_id ) {
				continue;
			}

			$other_order = wc_get_order( $other_order_id );
			if ( ! $other_order instanceof WC_Order || ! $other_order->is_paid() ) {
				continue;
			}

			if ( self::order_contains_product_ids( $other_order, $product_ids ) ) {
				return true;
			}
		}

		return false;
	}

	public static function queue_course_reconciliation( $meta_id, int $object_id, string $meta_key, $meta_value ): void {
		if ( self::COURSE_PRODUCT_META !== $meta_key || 'course' !== get_post_type( $object_id ) ) {
			return;
		}

		$args = [ $object_id ];
		if ( ! wp_next_scheduled( self::RECONCILE_HOOK, $args ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::RECONCILE_HOOK, $args );
		}
	}

	public static function schedule_daily_audit(): void {
		if ( ! wp_next_scheduled( self::DAILY_AUDIT_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::DAILY_AUDIT_HOOK );
		}
	}

	public static function run_scheduled_audit(): void {
		$result                = self::audit_grants( true );
		$result['completed_at'] = current_time( 'mysql' );
		update_option( self::LAST_AUDIT_OPTION, $result, false );
	}

	public static function reconcile_course_access( int $course_id ): array {
		$result = [
			'course_id' => $course_id,
			'checked'   => 0,
			'granted'   => 0,
			'orphaned'  => 0,
			'revoked'   => 0,
		];

		if ( ! $course_id || 'course' !== get_post_type( $course_id ) ) {
			return $result;
		}

		$audit              = self::audit_grants( true, $course_id );
		$result['checked']  = $audit['checked'];
		$result['orphaned'] = $audit['orphaned'];
		$result['revoked']  = $audit['revoked'];
		$product_ids        = self::get_product_ids_for_course( $course_id );

		if ( ! $product_ids ) {
			return $result;
		}

		foreach ( self::get_all_paid_order_ids() as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order instanceof WC_Order || ! $order->is_paid() || ! self::order_contains_product_ids( $order, $product_ids ) ) {
				continue;
			}

			$user_id = self::get_user_id_for_order( $order );
			if ( ! $user_id ) {
				continue;
			}

			$had_grant = self::bridge_user_has_grant( $user_id, $course_id );
			self::grant_order_access( (int) $order_id );
			if ( ! $had_grant && self::bridge_user_has_grant( $user_id, $course_id ) ) {
				++$result['granted'];
			}
		}

		return $result;
	}

	public static function audit_grants( bool $fix = false, int $course_id = 0 ): array {
		global $wpdb;

		$result = [
			'checked'  => 0,
			'valid'    => 0,
			'orphaned' => 0,
			'revoked'  => 0,
		];

		// Never interpret a temporarily unavailable WooCommerce order store as zero entitlement.
		if ( ! function_exists( 'wc_get_orders' ) || ! function_exists( 'wc_get_order' ) ) {
			return $result;
		}

		if ( $course_id ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
					self::USER_ACCESS_PREFIX . $course_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
					$wpdb->esc_like( self::USER_ACCESS_PREFIX ) . '%'
				)
			);
		}

		foreach ( $rows as $row ) {
			++$result['checked'];
			$row_course_id = absint( substr( (string) $row->meta_key, strlen( self::USER_ACCESS_PREFIX ) ) );
			$user_id       = absint( $row->user_id );

			if ( $row_course_id && self::user_has_paid_order_for_course( $user_id, $row_course_id ) ) {
				++$result['valid'];
				continue;
			}

			++$result['orphaned'];
			if ( ! $fix ) {
				continue;
			}

			if ( $row_course_id ) {
				self::revoke_user_course_access( $user_id, $row_course_id );
				self::remove_course_from_source_order_snapshot( $row->meta_value, $row_course_id, $user_id );
			} else {
				delete_user_meta( $user_id, (string) $row->meta_key );
			}

			if ( ! metadata_exists( 'user', $user_id, (string) $row->meta_key ) ) {
				++$result['revoked'];
			}
		}

		return $result;
	}

	public static function validate_checkout_account( array $data, WP_Error $errors ): void {
		if ( is_user_logged_in() || ! self::cart_contains_product_linked_course() ) {
			return;
		}

		$errors->add(
			'p1_sensei_woo_bridge_account_required',
			'Please log in or create an account before buying this course so access can be attached to your student account.'
		);
	}

	private static function cart_contains_product_linked_course(): bool {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			$product_id = self::get_cart_item_product_id( $item );
			if ( self::get_course_ids_for_product( $product_id ) ) {
				return true;
			}
		}

		return false;
	}

	private static function user_has_course_access( int $user_id, int $course_id ): bool {
		if ( ! $user_id ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) || function_exists( 'sensei_all_access' ) && sensei_all_access( $user_id ) ) {
			return true;
		}

		$access = get_user_meta( $user_id, self::USER_ACCESS_PREFIX . $course_id, true );
		if ( ! empty( $access ) ) {
			return true;
		}

		if ( ! function_exists( 'wc_customer_bought_product' ) ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		foreach ( self::get_product_ids_for_course( $course_id ) as $product_id ) {
			if ( wc_customer_bought_product( $user->user_email, $user_id, $product_id ) ) {
				return true;
			}
		}

		return false;
	}

	private static function grant_user_course_access( int $user_id, int $course_id, WC_Order $order ): bool {
		update_user_meta(
			$user_id,
			self::USER_ACCESS_PREFIX . $course_id,
			[
				'order_id'   => $order->get_id(),
				'product_id' => self::get_primary_product_id_for_course( $course_id ),
				'granted_at' => current_time( 'mysql' ),
			]
		);

		if ( class_exists( 'Sensei_Course_Enrolment' ) ) {
			try {
				return Sensei_Course_Enrolment::get_course_instance( $course_id )->enrol( $user_id );
			} catch ( Throwable $error ) {
				// Fall through to progress start below for early-hook or provider failures.
			}
		}

		if ( class_exists( 'Sensei_Utils' ) ) {
			return false !== Sensei_Utils::user_start_course( $user_id, $course_id );
		}

		return true;
	}

	private static function bridge_user_has_grant( int $user_id, int $course_id ): bool {
		return metadata_exists( 'user', $user_id, self::USER_ACCESS_PREFIX . $course_id )
			&& ! empty( get_user_meta( $user_id, self::USER_ACCESS_PREFIX . $course_id, true ) );
	}

	private static function get_order_granted_course_ids( WC_Order $order ): array {
		$course_ids = (array) $order->get_meta( self::ORDER_GRANTS_META, true );
		$course_ids = array_map( 'absint', $course_ids );

		return array_values( array_unique( array_filter( $course_ids ) ) );
	}

	private static function get_paid_order_statuses(): array {
		$statuses = function_exists( 'wc_get_is_paid_statuses' )
			? wc_get_is_paid_statuses()
			: [ 'processing', 'completed' ];

		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $statuses ) ) ) );
	}

	private static function get_paid_order_ids_for_user( int $user_id ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return [];
		}

		$query = [
			'status' => self::get_paid_order_statuses(),
			'type'   => 'shop_order',
			'limit'  => -1,
			'return' => 'ids',
		];

		$order_ids = wc_get_orders( array_merge( $query, [ 'customer_id' => $user_id ] ) );
		$user      = get_userdata( $user_id );

		if ( $user && $user->user_email ) {
			$order_ids = array_merge(
				$order_ids,
				wc_get_orders( array_merge( $query, [ 'billing_email' => $user->user_email ] ) )
			);
		}

		return array_values( array_unique( array_map( 'absint', $order_ids ) ) );
	}

	private static function get_all_paid_order_ids(): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return [];
		}

		$order_ids = wc_get_orders(
			[
				'status' => self::get_paid_order_statuses(),
				'type'   => 'shop_order',
				'limit'  => -1,
				'return' => 'ids',
			]
		);

		return array_values( array_unique( array_map( 'absint', $order_ids ) ) );
	}

	private static function order_contains_product_ids( WC_Order $order, array $product_ids ): bool {
		foreach ( $order->get_items() as $item ) {
			$item_product_ids = array_filter(
				[ (int) $item->get_product_id(), (int) $item->get_variation_id() ]
			);
			if ( array_intersect( $item_product_ids, $product_ids ) ) {
				return true;
			}
		}

		return false;
	}

	private static function remove_course_from_source_order_snapshot( $grant_meta, int $course_id, int $user_id ): void {
		$grant_meta = maybe_unserialize( $grant_meta );
		$order_id   = is_array( $grant_meta ) ? absint( $grant_meta['order_id'] ?? 0 ) : 0;
		$order      = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$course_ids = array_values( array_diff( self::get_order_granted_course_ids( $order ), [ $course_id ] ) );
		if ( $course_ids ) {
			$order->update_meta_data( self::ORDER_GRANTS_META, $course_ids );
		} else {
			$order->delete_meta_data( self::ORDER_GRANTS_META );
		}

		$order->add_order_note(
			sprintf(
				'P1 Sensei Woo Bridge: scheduled audit revoked orphaned user #%d access to course #%d.',
				$user_id,
				$course_id
			)
		);
		$order->save();
	}

	private static function get_user_id_for_order( WC_Order $order ): int {
		$user_id = $order->get_user_id();
		if ( $user_id ) {
			return (int) $user_id;
		}

		$email = $order->get_billing_email();
		if ( ! $email ) {
			return 0;
		}

		$user = get_user_by( 'email', $email );
		return $user ? (int) $user->ID : 0;
	}

	private static function get_course_ids_for_order( WC_Order $order ): array {
		$course_ids = [];
		foreach ( $order->get_items() as $item ) {
			$product_id = (int) $item->get_product_id();
			$variation_id = (int) $item->get_variation_id();

			foreach ( array_filter( [ $product_id, $variation_id ] ) as $item_product_id ) {
				$course_ids = array_merge( $course_ids, self::get_course_ids_for_product( $item_product_id ) );
			}
		}

		return array_values( array_unique( array_map( 'absint', $course_ids ) ) );
	}

	private static function get_course_ids_for_product( int $product_id ): array {
		if ( ! $product_id ) {
			return [];
		}

		$courses = get_posts(
			[
				'post_type'      => 'course',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'     => self::COURSE_PRODUCT_META,
						'value'   => (string) $product_id,
						'compare' => '=',
					],
				],
			]
		);

		return array_values( array_map( 'absint', $courses ) );
	}

	private static function get_course_id_for_sensei_content( int $content_id ): int {
		$post_type = get_post_type( $content_id );
		if ( 'course' === $post_type ) {
			return $content_id;
		}

		if ( 'quiz' === $post_type ) {
			$content_id = (int) get_post_meta( $content_id, '_quiz_lesson', true );
		}

		if ( 'lesson' === get_post_type( $content_id ) ) {
			return (int) get_post_meta( $content_id, '_lesson_course', true );
		}

		return 0;
	}

	private static function get_current_course_id(): int {
		if ( is_singular( 'course' ) ) {
			return (int) get_queried_object_id();
		}

		$post_id = (int) get_the_ID();
		return 'course' === get_post_type( $post_id ) ? $post_id : 0;
	}

	private static function is_product_linked_course( int $course_id ): bool {
		return ! empty( self::get_product_ids_for_course( $course_id ) );
	}

	private static function get_primary_product_id_for_course( int $course_id ): int {
		$product_ids = self::get_product_ids_for_course( $course_id );
		return $product_ids ? $product_ids[0] : 0;
	}

	private static function get_product_ids_for_course( int $course_id ): array {
		if ( ! $course_id ) {
			return [];
		}

		$meta_values = get_post_meta( $course_id, self::COURSE_PRODUCT_META, false );
		$product_ids = [];

		foreach ( $meta_values as $meta_value ) {
			if ( is_array( $meta_value ) ) {
				$product_ids = array_merge( $product_ids, $meta_value );
			} else {
				$product_ids[] = $meta_value;
			}
		}

		$product_ids = array_map( 'absint', $product_ids );
		$product_ids = array_filter( $product_ids );

		return array_values( array_unique( $product_ids ) );
	}

	private static function get_cart_item_product_id( array $item ): int {
		if ( ! empty( $item['variation_id'] ) ) {
			return (int) $item['variation_id'];
		}

		return isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
	}
}

P1_Sensei_Woo_Bridge::init();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	final class P1_Sensei_Woo_Bridge_CLI_Command {
		public function audit( array $args, array $assoc_args ): void {
			$fix    = isset( $assoc_args['fix'] );
			$result = P1_Sensei_Woo_Bridge::audit_grants( $fix );

			\WP_CLI\Utils\format_items( 'table', [ $result ], [ 'checked', 'valid', 'orphaned', 'revoked' ] );

			if ( $result['orphaned'] && ! $fix ) {
				WP_CLI::warning( 'Orphaned grants found. Run `wp p1-course-access audit --fix` to revoke them.' );
				return;
			}

			WP_CLI::success( $fix ? 'Course-access audit completed with repairs enabled.' : 'Course-access audit completed.' );
		}

		public function reconcile( array $args ): void {
			$course_id = absint( $args[0] ?? 0 );
			if ( ! $course_id ) {
				WP_CLI::error( 'Provide a course ID: `wp p1-course-access reconcile <course-id>`.' );
			}

			$result = P1_Sensei_Woo_Bridge::reconcile_course_access( $course_id );
			\WP_CLI\Utils\format_items( 'table', [ $result ], [ 'course_id', 'checked', 'granted', 'orphaned', 'revoked' ] );
			WP_CLI::success( sprintf( 'Course #%d access reconciled.', $course_id ) );
		}

		public function status(): void {
			$next = wp_next_scheduled( 'p1_sensei_woo_bridge_daily_audit' );
			$last = get_option( 'p1_sensei_woo_bridge_last_audit', [] );

			\WP_CLI\Utils\format_items(
				'table',
				[
					[
						'next_audit' => $next ? wp_date( 'Y-m-d H:i:s T', $next ) : 'not scheduled',
						'last_audit' => is_array( $last ) && ! empty( $last['completed_at'] ) ? $last['completed_at'] : 'not run',
						'orphaned'   => is_array( $last ) ? (int) ( $last['orphaned'] ?? 0 ) : 0,
						'revoked'    => is_array( $last ) ? (int) ( $last['revoked'] ?? 0 ) : 0,
					],
				],
				[ 'next_audit', 'last_audit', 'orphaned', 'revoked' ]
			);
		}
	}

	WP_CLI::add_command( 'p1-course-access', 'P1_Sensei_Woo_Bridge_CLI_Command' );
}
