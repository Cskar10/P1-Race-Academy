<?php
/**
 * Standalone regression checks for P1 Simple Site.
 *
 * Run with:
 * php tests/p1-simple-site-regression.php
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['p1_test_filters']    = [];
$GLOBALS['p1_test_actions']    = [];
$GLOBALS['p1_test_shortcodes'] = [];
$GLOBALS['p1_test_state']      = [
	'admin'           => false,
	'ajax'            => false,
	'cron'            => false,
	'page'            => 'online-coaching',
	'queried_id'      => 55,
	'in_the_loop'     => true,
	'is_main_query'   => true,
	'singular'        => false,
	'archive'         => false,
	'taxonomy'        => '',
	'commerce_checks' => [],
];

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['p1_test_filters'][ $hook ][ $priority ][] = [ $callback, $accepted_args ];
	return true;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['p1_test_actions'][ $hook ][ $priority ][] = [ $callback, $accepted_args ];
	return true;
}

function p1_test_remove_hook( string $type, $hook, $callback, $priority ): bool {
	$registry = 'actions' === $type ? 'p1_test_actions' : 'p1_test_filters';
	$entries  = $GLOBALS[ $registry ][ $hook ][ $priority ] ?? [];

	foreach ( $entries as $index => $entry ) {
		if ( $entry[0] === $callback ) {
			unset( $GLOBALS[ $registry ][ $hook ][ $priority ][ $index ] );
			return true;
		}
	}

	return false;
}

function remove_action( $hook, $callback, $priority = 10 ) {
	return p1_test_remove_hook( 'actions', $hook, $callback, $priority );
}

function remove_filter( $hook, $callback, $priority = 10 ) {
	return p1_test_remove_hook( 'filters', $hook, $callback, $priority );
}

function add_shortcode( $tag, $callback ) {
	$GLOBALS['p1_test_shortcodes'][ $tag ] = $callback;
}

function remove_shortcode( $tag ) {
	unset( $GLOBALS['p1_test_shortcodes'][ $tag ] );
}

function is_admin() {
	return $GLOBALS['p1_test_state']['admin'];
}

function wp_doing_ajax() {
	return $GLOBALS['p1_test_state']['ajax'];
}

function wp_doing_cron() {
	return $GLOBALS['p1_test_state']['cron'];
}

function is_page( $page = '' ) {
	$current = $GLOBALS['p1_test_state']['page'];
	return is_array( $page ) ? in_array( $current, $page, true ) : $current === $page;
}

function is_singular() {
	return $GLOBALS['p1_test_state']['singular'];
}

function is_post_type_archive() {
	return $GLOBALS['p1_test_state']['archive'];
}

function is_tax( $taxonomy = '' ) {
	return $GLOBALS['p1_test_state']['taxonomy'] === $taxonomy;
}

function get_queried_object_id() {
	return $GLOBALS['p1_test_state']['queried_id'];
}

function in_the_loop() {
	return $GLOBALS['p1_test_state']['in_the_loop'];
}

function is_main_query() {
	return $GLOBALS['p1_test_state']['is_main_query'];
}

function is_cart() {
	return ! empty( $GLOBALS['p1_test_state']['commerce_checks']['is_cart'] );
}

function is_checkout() {
	return ! empty( $GLOBALS['p1_test_state']['commerce_checks']['is_checkout'] );
}

function is_shop() {
	return ! empty( $GLOBALS['p1_test_state']['commerce_checks']['is_shop'] );
}

function is_product() {
	return ! empty( $GLOBALS['p1_test_state']['commerce_checks']['is_product'] );
}

function is_product_category() {
	return ! empty( $GLOBALS['p1_test_state']['commerce_checks']['is_product_category'] );
}

function is_product_tag() {
	return ! empty( $GLOBALS['p1_test_state']['commerce_checks']['is_product_tag'] );
}

final class P1_Checkout_Flow {
	public static function redirect_legacy_add_to_cart_links(): void {
	}

	public static function handle_buy_now_request(): void {
	}
}

final class P1_Sensei_Woo_Bridge {
	public static function grant_order_access(): void {
	}

	public static function grant_order_access_on_status_change(): void {
	}

	public static function validate_checkout_account(): void {
	}
}

final class P1_Purchase_Welcome_Flow {
	public static function start_for_paid_order(): void {
	}
}

final class P1_Video_Submission_Flow {
	public static function handle_submission(): void {
	}

	public static function render_order_received_next_step(): void {
	}

	public static function append_order_received_next_step(): void {
	}

	public static function render_form(): string {
		return '';
	}
}

function p1_reflection_prompt_register_rest_routes(): void {
}

add_action( 'wp_loaded', [ 'P1_Checkout_Flow', 'redirect_legacy_add_to_cart_links' ], 5 );
add_action( 'template_redirect', [ 'P1_Checkout_Flow', 'handle_buy_now_request' ], 5 );
add_action( 'woocommerce_payment_complete', [ 'P1_Sensei_Woo_Bridge', 'grant_order_access' ] );
add_action( 'woocommerce_order_status_processing', [ 'P1_Sensei_Woo_Bridge', 'grant_order_access' ] );
add_action( 'woocommerce_order_status_completed', [ 'P1_Sensei_Woo_Bridge', 'grant_order_access' ] );
add_action( 'woocommerce_order_status_changed', [ 'P1_Sensei_Woo_Bridge', 'grant_order_access_on_status_change' ], 10, 4 );
add_action( 'woocommerce_after_checkout_validation', [ 'P1_Sensei_Woo_Bridge', 'validate_checkout_account' ], 10, 2 );
add_action( 'woocommerce_payment_complete', [ 'P1_Purchase_Welcome_Flow', 'start_for_paid_order' ], 20 );
add_action( 'woocommerce_order_status_processing', [ 'P1_Purchase_Welcome_Flow', 'start_for_paid_order' ], 20 );
add_action( 'woocommerce_order_status_completed', [ 'P1_Purchase_Welcome_Flow', 'start_for_paid_order' ], 20 );
add_action( 'admin_post_p1_video_submission', [ 'P1_Video_Submission_Flow', 'handle_submission' ] );
add_action( 'admin_post_nopriv_p1_video_submission', [ 'P1_Video_Submission_Flow', 'handle_submission' ] );
add_action( 'woocommerce_thankyou', [ 'P1_Video_Submission_Flow', 'render_order_received_next_step' ], 20 );
add_filter( 'the_content', [ 'P1_Video_Submission_Flow', 'append_order_received_next_step' ], 20 );
add_shortcode( 'p1_video_submission_form', [ 'P1_Video_Submission_Flow', 'render_form' ] );
add_action( 'rest_api_init', 'p1_reflection_prompt_register_rest_routes' );

require dirname( __DIR__ ) . '/wp-content/mu-plugins/p1-simple-site.php';

function p1_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

p1_assert( isset( $GLOBALS['p1_test_filters']['woocommerce_is_purchasable'] ), 'Purchase filter was registered.' );
p1_assert( isset( $GLOBALS['p1_test_filters']['sensei_can_user_view_lesson'] ), 'Lesson access filter was registered.' );
p1_assert( isset( $GLOBALS['p1_test_actions']['template_redirect'][0] ), 'Early redirect was registered.' );

P1_Simple_Site::disable_legacy_integrations();
p1_assert(
	! p1_test_remove_hook( 'actions', 'template_redirect', [ 'P1_Checkout_Flow', 'handle_buy_now_request' ], 5 ),
	'Legacy buy-now handler is disabled.'
);
p1_assert(
	! p1_test_remove_hook( 'actions', 'woocommerce_payment_complete', [ 'P1_Sensei_Woo_Bridge', 'grant_order_access' ], 10 ),
	'Purchase-to-course grants are disabled.'
);
p1_assert(
	! p1_test_remove_hook( 'actions', 'woocommerce_payment_complete', [ 'P1_Purchase_Welcome_Flow', 'start_for_paid_order' ], 20 ),
	'Purchase welcome automation is disabled.'
);
p1_assert(
	! p1_test_remove_hook( 'actions', 'admin_post_nopriv_p1_video_submission', [ 'P1_Video_Submission_Flow', 'handle_submission' ], 10 ),
	'Public video submissions are disabled.'
);
p1_assert( ! isset( $GLOBALS['p1_test_shortcodes']['p1_video_submission_form'] ), 'Video submission shortcode is disabled.' );
p1_assert(
	! p1_test_remove_hook( 'actions', 'rest_api_init', 'p1_reflection_prompt_register_rest_routes', 10 ),
	'Reflection response API is disabled.'
);

p1_assert( false === P1_Simple_Site::deny_purchase( true ), 'Products are never purchasable.' );
p1_assert( [] === P1_Simple_Site::remove_payment_gateways( [ 'stripe' => new stdClass() ] ), 'Payment gateways are removed.' );
p1_assert( false === P1_Simple_Site::deny_course_access( true ), 'Course access is denied.' );

$safe_button = '<a class="wp-block-button__link" href="/contact/">Contact us</a>';
$buy_button  = '<a class="wp-block-button__link" href="/checkout/?p1_buy=129">Buy</a>';

p1_assert(
	$safe_button === P1_Simple_Site::remove_course_and_payment_blocks( $safe_button, [ 'blockName' => 'core/button' ] ),
	'Non-payment buttons stay available.'
);
p1_assert(
	'' === P1_Simple_Site::remove_course_and_payment_blocks( $buy_button, [ 'blockName' => 'core/button' ] ),
	'Checkout buttons are removed.'
);
p1_assert(
	'' === P1_Simple_Site::remove_course_and_payment_blocks( '<div>Module</div>', [ 'blockName' => 'sensei-lms/course-outline' ] ),
	'Sensei module blocks are removed.'
);
p1_assert(
	'' === P1_Simple_Site::remove_course_and_payment_blocks( '<div>Cart</div>', [ 'blockName' => 'woocommerce/cart' ] ),
	'WooCommerce blocks are removed.'
);
p1_assert(
	'' === P1_Simple_Site::remove_course_and_payment_blocks( '<div>Prompt</div>', [ 'blockName' => 'p1-race-academy/reflection-prompt' ] ),
	'Course reflection blocks are removed.'
);

p1_assert( '' === P1_Simple_Site::remove_course_shortcodes( false, 'h5p', [], [] ), 'H5P shortcodes are removed.' );
p1_assert( false === P1_Simple_Site::remove_course_shortcodes( false, 'gallery', [], [] ), 'Unrelated shortcodes are unchanged.' );

p1_assert( 'Coming soon' === P1_Simple_Site::filter_course_page_title( 'Online Coaching', 55 ), 'Course page heading says Coming soon.' );
p1_assert(
	str_contains( P1_Simple_Site::filter_course_page_content( '<p>Buy now</p>' ), 'coming soon' ),
	'Course sales content is replaced.'
);

$GLOBALS['p1_test_state']['in_the_loop'] = false;
p1_assert( 'Course' === P1_Simple_Site::filter_course_page_title( 'Course', 55 ), 'Navigation labels stay unchanged.' );
$GLOBALS['p1_test_state']['in_the_loop'] = true;

p1_assert( false === P1_Simple_Site::is_unavailable_request(), 'The Coming soon page does not redirect itself.' );

$GLOBALS['p1_test_state']['page'] = 'my-courses';
p1_assert( true === P1_Simple_Site::is_unavailable_request(), 'The student course dashboard is unavailable.' );

$GLOBALS['p1_test_state']['page'] = 'courses-overview';
p1_assert( true === P1_Simple_Site::is_unavailable_request(), 'The Sensei course overview is unavailable.' );

$GLOBALS['p1_test_state']['page']     = '';
$GLOBALS['p1_test_state']['taxonomy'] = 'module';
p1_assert( true === P1_Simple_Site::is_unavailable_request(), 'Course module archives are unavailable.' );

$GLOBALS['p1_test_state']['taxonomy']                     = '';
$GLOBALS['p1_test_state']['page']                         = '';
$GLOBALS['p1_test_state']['commerce_checks']['is_shop'] = true;
p1_assert( true === P1_Simple_Site::is_unavailable_request(), 'The shop is unavailable.' );

echo "PASS: P1 Simple Site regression suite.\n";
