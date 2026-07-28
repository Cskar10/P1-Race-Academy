<?php
/**
 * Plugin Name: P1 Account Nav
 * Description: Adds login-aware Account, Dashboard, and Shop links to the primary nav, and wires the Woo My Account hub through to the key student pages.
 * Version: 0.4.1
 * Author: P1 Race Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the Sensei "My Courses" student dashboard URL.
 * Prefers the configured Sensei page, falls back to the my-courses page, then a literal path.
 */
function p1_my_courses_url(): string {
	$url = '';

	if ( class_exists( 'Sensei_Course' ) && method_exists( 'Sensei_Course', 'get_my_courses_page_id' ) ) {
		$pid = (int) Sensei_Course::get_my_courses_page_id();
		if ( $pid > 0 ) {
			$maybe = get_permalink( $pid );
			if ( $maybe ) {
				$url = $maybe;
			}
		}
	}

	if ( ! $url ) {
		$page = get_page_by_path( 'my-courses' );
		if ( $page ) {
			$maybe = get_permalink( $page );
			if ( $maybe ) {
				$url = $maybe;
			}
		}
	}

	if ( ! $url ) {
		$url = home_url( '/my-courses/' );
	}

	return $url;
}

/**
 * Resolve the WooCommerce Shop page URL.
 */
function p1_shop_url(): string {
	$url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '';

	if ( ! $url ) {
		$page = get_page_by_path( 'shop' );
		if ( $page ) {
			$maybe = get_permalink( $page );
			if ( $maybe ) {
				$url = $maybe;
			}
		}
	}

	if ( ! $url ) {
		$url = home_url( '/shop/' );
	}

	return $url;
}

/**
 * Primary/mobile nav: add Account, Dashboard, and Shop links before Support.
 */
add_filter(
	'wp_nav_menu_items',
	function ( string $items, $args ): string {
		$location = is_object( $args ) && isset( $args->theme_location ) ? (string) $args->theme_location : '';
		if ( ! in_array( $location, [ 'primary', 'mobile' ], true ) ) {
			return $items;
		}

		$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
		if ( ! $account_url ) {
			return $items;
		}

		if ( is_user_logged_in() ) {
			$label      = 'primary' === $location ? 'Account' : 'My Account';
			$aria_label = 'My Account';
			$class      = 'p1-account-link';
		} else {
			$label      = 'Login';
			$aria_label = 'Login';
			$class      = 'p1-login-link';
		}

		$account_current   = function_exists( 'is_account_page' ) && is_account_page();
		$dashboard_current = is_page( 'my-courses' );
		$shop_current      = ( function_exists( 'is_shop' ) && is_shop() ) || is_page( 'shop' ) || is_post_type_archive( 'product' );

		$account_item = sprintf(
			'<li class="menu-item menu-item-type-custom menu-item-type-p1-account %s%s"><a href="%s" aria-label="%s"%s>%s</a></li>',
			esc_attr( $class ),
			$account_current ? ' current-menu-item' : '',
			esc_url( $account_url ),
			esc_attr( $aria_label ),
			$account_current ? ' aria-current="page"' : '',
			esc_html( $label )
		);

		$dashboard_item = sprintf(
			'<li class="menu-item menu-item-type-custom menu-item-type-p1-dashboard p1-dashboard-link%s"><a href="%s"%s>Dashboard</a></li>',
			$dashboard_current ? ' current-menu-item' : '',
			esc_url( p1_my_courses_url() ),
			$dashboard_current ? ' aria-current="page"' : ''
		);

		$shop_item = sprintf(
			'<li class="menu-item menu-item-type-custom menu-item-type-p1-shop p1-shop-link%s"><a href="%s"%s>Shop</a></li>',
			$shop_current ? ' current-menu-item' : '',
			esc_url( p1_shop_url() ),
			$shop_current ? ' aria-current="page"' : ''
		);

		$dynamic_items = $account_item . $dashboard_item . $shop_item;

		// The static Support item is the stable insertion point for the desired order.
		if ( str_contains( $items, 'p1-support-link' ) ) {
			$items = preg_replace(
				'/(?=<li\b[^>]*\bp1-support-link\b)/',
				$dynamic_items,
				$items,
				1
			);

			return is_string( $items ) ? $items : $dynamic_items;
		}

		$items .= $dynamic_items;

		return $items;
	},
	20,
	2
);

/**
 * Relabel Downloads as Shop and add a "My Courses" item just before Logout.
 */
add_filter(
	'woocommerce_account_menu_items',
	function ( array $items ): array {
		if ( isset( $items['downloads'] ) ) {
			$items['downloads'] = 'Shop';
		}

		$new = [];
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$new['p1-my-courses'] = 'My Courses';
			}
			$new[ $key ] = $label;
		}
		if ( ! isset( $new['p1-my-courses'] ) ) {
			$new['p1-my-courses'] = 'My Courses';
		}
		return $new;
	}
);

/**
 * Point the custom account menu items at their real destinations.
 */
add_filter(
	'woocommerce_get_endpoint_url',
	function ( $url, $endpoint, $value, $permalink ) {
		if ( 'downloads' === $endpoint ) {
			return p1_shop_url();
		}

		if ( 'p1-my-courses' === $endpoint ) {
			return p1_my_courses_url();
		}
		return $url;
	},
	10,
	4
);

/**
 * Prominent "Go to My Courses" button at the top of the My Account dashboard,
 * so the account hub leads straight through to the student course dashboard.
 */
add_action(
	'woocommerce_account_dashboard',
	function (): void {
		printf(
			'<p class="p1-mycourses-cta" style="margin:0 0 1.5em;"><a class="button" href="%s">Go to My Courses &rarr;</a></p>',
			esc_url( p1_my_courses_url() )
		);
	},
	5
);
