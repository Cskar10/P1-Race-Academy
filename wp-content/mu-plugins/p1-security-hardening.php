<?php
/**
 * Plugin Name: P1 Security Hardening
 * Description: Local-safe WordPress hardening for P1 Race Academy: browser headers, user-enumeration reduction, XML-RPC blocking, and generic login errors.
 * Version: 0.1.0
 * Author: P1 Race Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'send_headers', 'p1_security_hardening_headers', 0 );
add_filter( 'rest_pre_serve_request', 'p1_security_hardening_rest_headers', 0, 4 );
add_filter( 'rest_pre_dispatch', 'p1_security_hardening_private_user_rest', 10, 3 );
add_filter( 'redirect_canonical', 'p1_security_hardening_disable_author_canonical', 10, 2 );
add_action( 'template_redirect', 'p1_security_hardening_hide_author_archives', 0 );
add_action( 'init', 'p1_security_hardening_block_xmlrpc', 0 );
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'wp_headers', 'p1_security_hardening_remove_pingback_header' );
add_filter( 'login_errors', 'p1_security_hardening_login_error' );

/**
 * Send conservative browser security headers that are safe for the current stack.
 *
 * HSTS is intentionally left to Rocket.net/Cloudflare after HTTPS is live. LocalWP
 * runs over HTTP, and preloading the wrong domain would be a bad kind of brave.
 */
function p1_security_hardening_headers(): void {
	if ( headers_sent() ) {
		return;
	}

	header_remove( 'X-Powered-By' );

	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( "Content-Security-Policy: frame-ancestors 'self'" );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), geolocation=(), microphone=()' );
}

/**
 * REST requests do not always pass through the normal `send_headers` path.
 */
function p1_security_hardening_rest_headers( $served, $result, WP_REST_Request $request, WP_REST_Server $server ) {
	unset( $result, $request, $server );

	p1_security_hardening_headers();

	return $served;
}

/**
 * Keep public WordPress user metadata out of unauthenticated REST responses.
 */
function p1_security_hardening_private_user_rest( $result, WP_REST_Server $server, WP_REST_Request $request ) {
	unset( $server );

	if ( null !== $result ) {
		return $result;
	}

	$route = $request->get_route();
	if ( ! str_starts_with( $route, '/wp/v2/users' ) ) {
		return $result;
	}

	if ( '/wp/v2/users/me' === $route && is_user_logged_in() ) {
		return $result;
	}

	if ( current_user_can( 'list_users' ) ) {
		return $result;
	}

	return new WP_Error(
		'p1_user_directory_private',
		'User directory access is private.',
		[ 'status' => rest_authorization_required_code() ]
	);
}

/**
 * Stop `/?author=1` from redirecting to a public author slug for logged-out users.
 */
function p1_security_hardening_disable_author_canonical( $redirect_url, $requested_url ) {
	unset( $requested_url );

	if ( is_author() && ! is_user_logged_in() ) {
		return false;
	}

	return $redirect_url;
}

/**
 * Hide public author archives on this course site.
 */
function p1_security_hardening_hide_author_archives(): void {
	if ( ! is_author() || is_user_logged_in() ) {
		return;
	}

	global $wp_query;

	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();

	$template = get_404_template();
	if ( $template ) {
		include $template;
	}

	exit;
}

/**
 * XML-RPC is not used by the P1 stack. Block it before method handling.
 */
function p1_security_hardening_block_xmlrpc(): void {
	if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
		return;
	}

	p1_security_hardening_headers();
	status_header( 403 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo 'XML-RPC is disabled.';
	exit;
}

/**
 * Avoid advertising XML-RPC pingback support.
 */
function p1_security_hardening_remove_pingback_header( array $headers ): array {
	unset( $headers['X-Pingback'] );

	return $headers;
}

/**
 * Do not reveal whether the username or password was the bad part.
 */
function p1_security_hardening_login_error(): string {
	return 'Invalid login details.';
}
