<?php
/**
 * Plugin Name: P1 HSTS Header
 * Description: Sends Strict-Transport-Security on the production host only.
 */
add_action( 'send_headers', function () {
	$host = strtolower( $_SERVER['HTTP_HOST'] ?? '' );
	if ( preg_match( '/(^|\.)p1raceacademy\.com$/', $host ) ) {
		header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
	}
} );
