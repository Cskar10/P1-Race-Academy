<?php
/**
 * Plugin Name:       P1 Race Academy Blocks
 * Description:       Custom learning blocks for P1 Race Academy.
 * Version:           0.4.1
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Author:            P1 Race Academy
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       go-kart-blocks
 *
 * @package P1RaceAcademy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Registers the block(s) metadata from the `blocks-manifest.php` and registers the block type(s)
 * based on the registered block metadata. Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://make.wordpress.org/core/2025/03/13/more-efficient-block-type-registration-in-6-8/
 * @see https://make.wordpress.org/core/2024/10/17/new-block-type-registration-apis-to-improve-performance-in-wordpress-6-7/
 */
function p1_race_academy_go_kart_blocks_block_init() {
	wp_register_block_types_from_metadata_collection( __DIR__ . '/build', __DIR__ . '/build/blocks-manifest.php' );
}
add_action( 'init', 'p1_race_academy_go_kart_blocks_block_init' );

const P1_REFLECTION_PROMPT_META_KEY = '_p1_reflection_prompt_responses';
const P1_REFLECTION_PROMPT_BLOCK    = 'p1-race-academy/reflection-prompt';

/**
 * Registers the private per-student reflection response API.
 */
function p1_reflection_prompt_register_rest_routes() {
	register_rest_route(
		'p1-race-academy/v1',
		'/reflections/(?P<lesson_id>\d+)/(?P<response_id>[a-z0-9-]+)',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'p1_reflection_prompt_get_response',
				'permission_callback' => 'p1_reflection_prompt_can_access',
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => 'p1_reflection_prompt_save_response',
				'permission_callback' => 'p1_reflection_prompt_can_access',
				'args'                => array(
					'response' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
						'validate_callback' => static function ( $value ) {
							return is_string( $value ) && strlen( $value ) <= 5000;
						},
					),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'p1_reflection_prompt_register_rest_routes' );

/**
 * Adds the current lesson and REST nonce to the frontend block script.
 */
function p1_reflection_prompt_localize_view_script() {
	if ( ! is_singular( 'lesson' ) ) {
		return;
	}

	wp_localize_script(
		'p1-race-academy-reflection-prompt-view-script',
		'p1ReflectionPromptSettings',
		array(
			'isLoggedIn' => is_user_logged_in(),
			'lessonId'   => get_queried_object_id(),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'restUrl'    => esc_url_raw( rest_url() ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'p1_reflection_prompt_localize_view_script' );

/**
 * Allows only logged-in users who can view the lesson and a real prompt block.
 */
function p1_reflection_prompt_can_access( WP_REST_Request $request ) {
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'p1_reflection_login_required', 'Log in to access reflection responses.', array( 'status' => 401 ) );
	}

	$lesson_id   = absint( $request['lesson_id'] );
	$response_id = sanitize_key( $request['response_id'] );

	if ( 'lesson' !== get_post_type( $lesson_id ) ) {
		return new WP_Error( 'p1_reflection_invalid_lesson', 'Reflection lesson not found.', array( 'status' => 404 ) );
	}

	if ( function_exists( 'sensei_can_user_view_lesson' ) && ! sensei_can_user_view_lesson( $lesson_id, get_current_user_id() ) ) {
		return new WP_Error( 'p1_reflection_forbidden', 'You do not have access to this lesson.', array( 'status' => 403 ) );
	}

	if ( ! p1_reflection_prompt_lesson_has_response_id( $lesson_id, $response_id ) ) {
		return new WP_Error( 'p1_reflection_not_found', 'Reflection prompt not found in this lesson.', array( 'status' => 404 ) );
	}

	return true;
}

/**
 * Returns the current student's saved response.
 */
function p1_reflection_prompt_get_response( WP_REST_Request $request ) {
	$key       = p1_reflection_prompt_response_key( absint( $request['lesson_id'] ), sanitize_key( $request['response_id'] ) );
	$responses = p1_reflection_prompt_user_responses();
	$saved     = $responses[ $key ] ?? array();

	return rest_ensure_response(
		array(
			'response'  => $saved['response'] ?? '',
			'updatedAt' => $saved['updated_at'] ?? null,
		)
	);
}

/**
 * Saves the current student's response without exposing other users' data.
 */
function p1_reflection_prompt_save_response( WP_REST_Request $request ) {
	$key       = p1_reflection_prompt_response_key( absint( $request['lesson_id'] ), sanitize_key( $request['response_id'] ) );
	$responses = p1_reflection_prompt_user_responses();

	$responses[ $key ] = array(
		'response'   => sanitize_textarea_field( $request->get_param( 'response' ) ),
		'updated_at' => current_time( 'mysql', true ),
	);

	update_user_meta( get_current_user_id(), P1_REFLECTION_PROMPT_META_KEY, $responses );

	return rest_ensure_response( $responses[ $key ] );
}

/**
 * Gets the current user's full response map.
 */
function p1_reflection_prompt_user_responses() {
	$responses = get_user_meta( get_current_user_id(), P1_REFLECTION_PROMPT_META_KEY, true );
	return is_array( $responses ) ? $responses : array();
}

/**
 * Creates a user-meta map key for one lesson block.
 */
function p1_reflection_prompt_response_key( $lesson_id, $response_id ) {
	return absint( $lesson_id ) . ':' . sanitize_key( $response_id );
}

/**
 * Confirms that the requested prompt ID exists in the requested lesson.
 */
function p1_reflection_prompt_lesson_has_response_id( $lesson_id, $response_id ) {
	return p1_reflection_prompt_blocks_have_response_id( parse_blocks( get_post_field( 'post_content', $lesson_id ) ), $response_id );
}

/**
 * Recursively searches nested blocks for a reflection response ID.
 */
function p1_reflection_prompt_blocks_have_response_id( $blocks, $response_id ) {
	foreach ( $blocks as $block ) {
		if (
			P1_REFLECTION_PROMPT_BLOCK === ( $block['blockName'] ?? '' )
			&& $response_id === sanitize_key( $block['attrs']['responseId'] ?? '' )
		) {
			return true;
		}

		if ( ! empty( $block['innerBlocks'] ) && p1_reflection_prompt_blocks_have_response_id( $block['innerBlocks'], $response_id ) ) {
			return true;
		}
	}

	return false;
}
