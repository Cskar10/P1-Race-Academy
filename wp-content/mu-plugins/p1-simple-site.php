<?php
/**
 * Plugin Name: P1 Simple Site
 * Description: Keeps the public P1 site and navigation online while courses and payments are unavailable.
 * Version: 0.1.2
 * Author: P1 Race Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class P1_Simple_Site {
	private const COURSE_PAGE_SLUG = 'online-coaching';
	private const COMING_SOON      = 'Coming soon';

	public static function init(): void {
		add_action( 'plugins_loaded', [ __CLASS__, 'disable_legacy_integrations' ], PHP_INT_MAX );
		add_action( 'template_redirect', [ __CLASS__, 'redirect_unavailable_pages' ], 0 );

		add_filter( 'woocommerce_is_purchasable', [ __CLASS__, 'deny_purchase' ], PHP_INT_MAX );
		add_filter( 'woocommerce_variation_is_purchasable', [ __CLASS__, 'deny_purchase' ], PHP_INT_MAX );
		add_filter( 'woocommerce_add_to_cart_validation', [ __CLASS__, 'deny_purchase' ], PHP_INT_MAX );
		add_filter( 'woocommerce_available_payment_gateways', [ __CLASS__, 'remove_payment_gateways' ], PHP_INT_MAX );

		add_filter( 'sensei_can_user_manually_enrol', [ __CLASS__, 'deny_course_access' ], PHP_INT_MAX );
		add_filter( 'sensei_can_access_course_content', [ __CLASS__, 'deny_course_access' ], PHP_INT_MAX );
		add_filter( 'sensei_can_user_view_lesson', [ __CLASS__, 'deny_course_access' ], PHP_INT_MAX );

		add_filter( 'render_block', [ __CLASS__, 'remove_course_and_payment_blocks' ], PHP_INT_MAX, 2 );
		add_filter( 'pre_do_shortcode_tag', [ __CLASS__, 'remove_course_shortcodes' ], PHP_INT_MAX, 4 );
		add_filter( 'the_title', [ __CLASS__, 'filter_course_page_title' ], PHP_INT_MAX, 2 );
		add_filter( 'document_title_parts', [ __CLASS__, 'filter_document_title' ], PHP_INT_MAX );
		add_filter( 'the_content', [ __CLASS__, 'filter_course_page_content' ], PHP_INT_MAX );
	}

	/**
	 * Stop the existing custom purchase and course-intake handlers without
	 * deleting their source or persistent data. Switching back to main restores
	 * them normally.
	 */
	public static function disable_legacy_integrations(): void {
		if ( class_exists( 'P1_Checkout_Flow' ) ) {
			remove_action( 'wp_loaded', [ 'P1_Checkout_Flow', 'redirect_legacy_add_to_cart_links' ], 5 );
			remove_action( 'template_redirect', [ 'P1_Checkout_Flow', 'handle_buy_now_request' ], 5 );
		}

		if ( class_exists( 'P1_Sensei_Woo_Bridge' ) ) {
			remove_action( 'woocommerce_payment_complete', [ 'P1_Sensei_Woo_Bridge', 'grant_order_access' ] );
			remove_action( 'woocommerce_order_status_processing', [ 'P1_Sensei_Woo_Bridge', 'grant_order_access' ] );
			remove_action( 'woocommerce_order_status_completed', [ 'P1_Sensei_Woo_Bridge', 'grant_order_access' ] );
			remove_action( 'woocommerce_order_status_changed', [ 'P1_Sensei_Woo_Bridge', 'grant_order_access_on_status_change' ], 10 );
			remove_action( 'woocommerce_after_checkout_validation', [ 'P1_Sensei_Woo_Bridge', 'validate_checkout_account' ], 10 );
		}

		if ( class_exists( 'P1_Purchase_Welcome_Flow' ) ) {
			remove_action( 'woocommerce_payment_complete', [ 'P1_Purchase_Welcome_Flow', 'start_for_paid_order' ], 20 );
			remove_action( 'woocommerce_order_status_processing', [ 'P1_Purchase_Welcome_Flow', 'start_for_paid_order' ], 20 );
			remove_action( 'woocommerce_order_status_completed', [ 'P1_Purchase_Welcome_Flow', 'start_for_paid_order' ], 20 );
		}

		if ( class_exists( 'P1_Video_Submission_Flow' ) ) {
			remove_action( 'admin_post_p1_video_submission', [ 'P1_Video_Submission_Flow', 'handle_submission' ] );
			remove_action( 'admin_post_nopriv_p1_video_submission', [ 'P1_Video_Submission_Flow', 'handle_submission' ] );
			remove_action( 'woocommerce_thankyou', [ 'P1_Video_Submission_Flow', 'render_order_received_next_step' ], 20 );
			remove_filter( 'the_content', [ 'P1_Video_Submission_Flow', 'append_order_received_next_step' ], 20 );
		}

		if ( function_exists( 'remove_shortcode' ) ) {
			remove_shortcode( 'p1_video_submission_form' );
		}

		if ( function_exists( 'p1_reflection_prompt_register_rest_routes' ) ) {
			remove_action( 'rest_api_init', 'p1_reflection_prompt_register_rest_routes' );
		}
	}

	public static function deny_purchase( $allowed ): bool {
		unset( $allowed );
		return false;
	}

	public static function remove_payment_gateways( $gateways ): array {
		unset( $gateways );
		return [];
	}

	public static function deny_course_access( $allowed ): bool {
		unset( $allowed );
		return false;
	}

	public static function remove_course_and_payment_blocks( $block_content, array $block ): string {
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return (string) $block_content;
		}

		$block_name = (string) ( $block['blockName'] ?? '' );

		if (
			str_starts_with( $block_name, 'sensei-lms/' )
			|| str_starts_with( $block_name, 'woocommerce/' )
			|| 'p1-race-academy/reflection-prompt' === $block_name
		) {
			return '';
		}

		if (
			'core/button' === $block_name
			&& preg_match( '#(?:/checkout/?|[?&](?:p1_buy|p1_buy_online_coaching|add-to-cart)=)#i', (string) $block_content )
		) {
			return '';
		}

		return (string) $block_content;
	}

	public static function remove_course_shortcodes( $return, $tag, $attr, $match ) {
		unset( $attr, $match );

		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return $return;
		}

		if ( in_array( (string) $tag, [ 'h5p', 'p1_video_submission_form' ], true ) ) {
			return '';
		}

		return $return;
	}

	public static function filter_course_page_title( $title, $post_id ): string {
		if (
			! self::is_non_frontend_request()
			&& self::is_course_page()
			&& self::queried_object_id() === (int) $post_id
		) {
			return self::COMING_SOON;
		}

		return (string) $title;
	}

	public static function filter_document_title( array $parts ): array {
		if ( self::is_course_page() ) {
			$parts['title'] = self::COMING_SOON;
		}

		return $parts;
	}

	public static function filter_course_page_content( $content ): string {
		if ( ! self::is_main_course_page_item( self::queried_object_id() ) ) {
			return (string) $content;
		}

		return '<h1 class="p1-simple-coming-soon">Coming soon</h1>';
	}

	public static function redirect_unavailable_pages(): void {
		if ( self::is_non_frontend_request() || ! self::is_unavailable_request() ) {
			return;
		}

		wp_safe_redirect( self::course_page_url(), 302, 'P1 Simple Site' );
		exit;
	}

	public static function is_unavailable_request(): bool {
		if ( self::is_course_page() ) {
			return false;
		}

		if (
			( function_exists( 'is_page' ) && is_page( [ 'my-courses', 'courses-overview', 'course-completed' ] ) )
			|| ( function_exists( 'is_singular' ) && is_singular( [ 'course', 'lesson', 'quiz' ] ) )
			|| ( function_exists( 'is_post_type_archive' ) && is_post_type_archive( [ 'course', 'lesson', 'quiz' ] ) )
			|| ( function_exists( 'is_tax' ) && is_tax( 'module' ) )
		) {
			return true;
		}

		foreach ( [ 'is_cart', 'is_checkout', 'is_shop', 'is_product', 'is_product_category', 'is_product_tag' ] as $condition ) {
			if ( function_exists( $condition ) && $condition() ) {
				return true;
			}
		}

		if ( function_exists( 'is_page' ) && is_page( [ 'cart', 'checkout', 'shop' ] ) ) {
			return true;
		}

		foreach ( [ 'p1_buy', 'p1_buy_online_coaching', 'add-to-cart' ] as $query_arg ) {
			if ( isset( $_GET[ $query_arg ] ) ) {
				return true;
			}
		}

		return false;
	}

	private static function is_main_course_page_item( int $post_id ): bool {
		return ! self::is_non_frontend_request()
			&& self::is_course_page()
			&& self::queried_object_id() === $post_id
			&& function_exists( 'in_the_loop' )
			&& in_the_loop()
			&& function_exists( 'is_main_query' )
			&& is_main_query();
	}

	private static function is_course_page(): bool {
		return function_exists( 'is_page' ) && is_page( self::COURSE_PAGE_SLUG );
	}

	private static function queried_object_id(): int {
		return function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
	}

	private static function is_non_frontend_request(): bool {
		return ( function_exists( 'is_admin' ) && is_admin() )
			|| ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() )
			|| ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() )
			|| ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}

	private static function course_page_url(): string {
		if ( function_exists( 'get_page_by_path' ) && function_exists( 'get_permalink' ) ) {
			$page = get_page_by_path( self::COURSE_PAGE_SLUG );
			if ( $page ) {
				$url = get_permalink( $page );
				if ( $url ) {
					return (string) $url;
				}
			}
		}

		return home_url( '/' . self::COURSE_PAGE_SLUG . '/' );
	}
}

P1_Simple_Site::init();
