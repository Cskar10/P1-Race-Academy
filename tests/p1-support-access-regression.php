<?php
/**
 * Standalone regression checks for P1 Support Access.
 *
 * Run with:
 * php tests/p1-support-access-regression.php
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['p1_test_actions'] = [];
$GLOBALS['p1_test_state']   = [
	'logged_in' => false,
	'page'      => 'contact',
];

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['p1_test_actions'][ $hook ][ $priority ][] = [ $callback, $accepted_args ];
	return true;
}

function is_user_logged_in() {
	return $GLOBALS['p1_test_state']['logged_in'];
}

function is_page( $page = '' ) {
	return $GLOBALS['p1_test_state']['page'] === $page;
}

function get_page_by_path( $slug ) {
	return (object) [ 'post_name' => $slug ];
}

function get_permalink( $page ) {
	return home_url( '/' . $page->post_name . '/' );
}

function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

function wc_get_page_permalink( $page ) {
	return 'myaccount' === $page ? home_url( '/my-account/' ) : '';
}

function add_query_arg( $key, $value, $url ) {
	return $url . ( str_contains( $url, '?' ) ? '&' : '?' )
		. rawurlencode( $key ) . '=' . rawurlencode( $value );
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function wp_unslash( $value ) {
	return $value;
}

function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

require dirname( __DIR__ ) . '/wp-content/mu-plugins/p1-support-access.php';

function p1_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

p1_assert( isset( $GLOBALS['p1_test_actions']['template_redirect'][0] ), 'Early Support redirect was registered.' );
p1_assert( isset( $GLOBALS['p1_test_actions']['woocommerce_login_form_end'][10] ), 'Login return field was registered.' );
p1_assert( isset( $GLOBALS['p1_test_actions']['woocommerce_register_form_end'][10] ), 'Registration return field was registered.' );
p1_assert(
	isset( $GLOBALS['p1_test_actions']['admin_post_nopriv_p1_video_submission'][0] ),
	'Guest video submissions are intercepted before the submission handler.'
);

p1_assert( true === P1_Support_Access::should_gate_support(), 'Guests must log in before opening Support.' );
p1_assert(
	'https://example.test/my-account/?p1_support_access=1' === P1_Support_Access::support_login_url(),
	'Guests are sent to the existing account login/registration screen.'
);

$GLOBALS['p1_test_state']['logged_in'] = true;
p1_assert( false === P1_Support_Access::should_gate_support(), 'Logged-in account holders can open Support.' );

$GLOBALS['p1_test_state']['logged_in'] = false;
$GLOBALS['p1_test_state']['page']      = 'privacy-policy';
p1_assert( false === P1_Support_Access::should_gate_support(), 'Unrelated pages remain public.' );

$_GET['p1_support_access'] = '1';
ob_start();
P1_Support_Access::render_support_redirect_field();
$support_redirect_field = (string) ob_get_clean();
unset( $_GET['p1_support_access'] );
p1_assert(
	str_contains( $support_redirect_field, 'name="redirect"' )
		&& str_contains( $support_redirect_field, 'https://example.test/contact/' ),
	'Login and registration return account holders to Support.'
);

$_GET['p1_support_access'] = 'https://evil.example/';
ob_start();
P1_Support_Access::render_support_redirect_field();
$unsafe_redirect_field = (string) ob_get_clean();
unset( $_GET['p1_support_access'] );
p1_assert( '' === $unsafe_redirect_field, 'Arbitrary redirect values are rejected.' );

echo "PASS: P1 Support Access regression suite.\n";
