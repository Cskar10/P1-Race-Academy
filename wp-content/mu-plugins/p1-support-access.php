<?php
/**
 * Plugin Name: P1 Support Access
 * Description: Requires an account before visitors can access Support or submit coaching footage.
 * Version: 0.1.0
 * Author: P1 Race Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class P1_Support_Access {
	private const SUPPORT_PAGE_SLUG  = 'contact';
	private const SUPPORT_RETURN_ARG = 'p1_support_access';

	public static function init(): void {
		add_action( 'template_redirect', [ __CLASS__, 'redirect_guest_support' ], 0 );
		add_action( 'woocommerce_login_form_end', [ __CLASS__, 'render_support_redirect_field' ] );
		add_action( 'woocommerce_register_form_end', [ __CLASS__, 'render_support_redirect_field' ] );
		add_action( 'admin_post_nopriv_p1_video_submission', [ __CLASS__, 'redirect_guest_submission' ], 0 );
	}

	public static function redirect_guest_support(): void {
		if ( ! self::should_gate_support() ) {
			return;
		}

		self::redirect_to_login( 302 );
	}

	public static function redirect_guest_submission(): void {
		self::redirect_to_login( 303 );
	}

	public static function should_gate_support(): bool {
		return function_exists( 'is_page' )
			&& is_page( self::SUPPORT_PAGE_SLUG )
			&& function_exists( 'is_user_logged_in' )
			&& ! is_user_logged_in();
	}

	public static function render_support_redirect_field(): void {
		if ( ! self::is_support_access_request() ) {
			return;
		}

		printf(
			'<input type="hidden" name="redirect" value="%s">' . "\n",
			esc_attr( self::support_page_url() )
		);
	}

	public static function support_login_url(): string {
		$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';

		if ( ! $url && function_exists( 'get_page_by_path' ) && function_exists( 'get_permalink' ) ) {
			$page = get_page_by_path( 'my-account' );
			if ( $page ) {
				$url = get_permalink( $page );
			}
		}

		if ( ! $url ) {
			$url = home_url( '/my-account/' );
		}

		return add_query_arg( self::SUPPORT_RETURN_ARG, '1', $url );
	}

	private static function redirect_to_login( int $status ): void {
		if ( function_exists( 'nocache_headers' ) ) {
			nocache_headers();
		}

		wp_safe_redirect( self::support_login_url(), $status, 'P1 Support Access' );
		exit;
	}

	private static function is_support_access_request(): bool {
		return isset( $_GET[ self::SUPPORT_RETURN_ARG ] )
			&& '1' === sanitize_key( wp_unslash( $_GET[ self::SUPPORT_RETURN_ARG ] ) );
	}

	private static function support_page_url(): string {
		if ( function_exists( 'get_page_by_path' ) && function_exists( 'get_permalink' ) ) {
			$page = get_page_by_path( self::SUPPORT_PAGE_SLUG );
			if ( $page ) {
				$url = get_permalink( $page );
				if ( $url ) {
					return (string) $url;
				}
			}
		}

		return home_url( '/' . self::SUPPORT_PAGE_SLUG . '/' );
	}
}

P1_Support_Access::init();
