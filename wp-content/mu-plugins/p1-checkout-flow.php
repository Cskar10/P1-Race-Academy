<?php
/**
 * Plugin Name: P1 Checkout Flow
 * Description: Local checkout hardening for the P1 course purchase paths (driver, parent, and full-access bundle).
 * Version: 0.2.0
 * Author: P1 Race Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class P1_Checkout_Flow {
	private const COURSE_PRODUCT_META = '_course_woocommerce_product';
	private const BUY_ARG             = 'p1_buy';
	// Back-compat: the original single-product "buy now" links used this arg.
	private const LEGACY_BUY_ARG      = 'p1_buy_online_coaching';
	private const LEGACY_PRODUCT_ID   = 82;

	public static function init(): void {
		add_action( 'wp_loaded', [ __CLASS__, 'redirect_legacy_add_to_cart_links' ], 5 );
		add_action( 'template_redirect', [ __CLASS__, 'handle_buy_now_request' ], 5 );
	}

	/**
	 * Express "buy now" URL for a specific product. Each course links its own
	 * product(s), so the product id must be explicit now that there are several.
	 */
	public static function get_buy_now_url( int $product_id = 0 ): string {
		$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );

		if ( $product_id > 0 ) {
			return add_query_arg( self::BUY_ARG, $product_id, $checkout_url );
		}

		return $checkout_url;
	}

	public static function redirect_legacy_add_to_cart_links(): void {
		if ( ! isset( $_GET['add-to-cart'] ) ) {
			return;
		}

		$product_id = absint( wp_unslash( $_GET['add-to-cart'] ) );
		if ( ! $product_id || ! self::is_course_product( $product_id ) ) {
			return;
		}

		wp_safe_redirect( self::get_buy_now_url( $product_id ) );
		exit;
	}

	public static function handle_buy_now_request(): void {
		$product_id = self::requested_product_id();
		if ( ! $product_id ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_get_checkout_url' ) ) {
			return;
		}

		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		if ( ! $product || ! $product->is_purchasable() ) {
			return;
		}

		self::ensure_cart();

		if ( ! WC()->cart ) {
			return;
		}

		wc_clear_notices();
		self::ensure_product_in_cart( $product_id );
		WC()->cart->calculate_totals();

		wp_safe_redirect( remove_query_arg( [ self::BUY_ARG, self::LEGACY_BUY_ARG ], wc_get_checkout_url() ) );
		exit;
	}

	private static function requested_product_id(): int {
		if ( isset( $_GET[ self::BUY_ARG ] ) ) {
			return absint( wp_unslash( $_GET[ self::BUY_ARG ] ) );
		}

		if ( isset( $_GET[ self::LEGACY_BUY_ARG ] ) ) {
			return self::LEGACY_PRODUCT_ID;
		}

		return 0;
	}

	private static function ensure_cart(): void {
		if ( null === WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
	}

	private static function ensure_product_in_cart( int $product_id ): void {
		$existing_key = self::get_cart_item_key( $product_id );

		if ( $existing_key ) {
			WC()->cart->set_quantity( $existing_key, 1, false );
			return;
		}

		WC()->cart->add_to_cart( $product_id, 1 );
	}

	private static function get_cart_item_key( int $product_id ): string {
		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			$item_product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
			if ( $product_id === $item_product_id ) {
				return (string) $cart_item_key;
			}
		}

		return '';
	}

	/**
	 * Whether the product is linked to at least one Sensei course. Keeps the
	 * express-buy redirect scoped to our course products only.
	 */
	private static function is_course_product( int $product_id ): bool {
		if ( ! $product_id ) {
			return false;
		}

		$courses = get_posts(
			[
				'post_type'      => 'course',
				'post_status'    => 'any',
				'posts_per_page' => 1,
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

		return ! empty( $courses );
	}
}

P1_Checkout_Flow::init();
