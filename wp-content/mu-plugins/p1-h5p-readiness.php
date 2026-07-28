<?php
/**
 * Plugin Name: P1 H5P Readiness
 * Description: WP-CLI readiness audit for P1 Race Academy H5P lesson interactivity.
 * Version: 0.4.7
 * Author: P1 Race Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

final class P1_H5P_Readiness_Command {
	private const LESSON_ONE_ID = 69;
	private const LESSON_ONE_H5P_ID = 1;
	private const LESSON_ONE_H5P_TITLE = 'Lesson 1 footage checklist';
	private const BRANCHING_SCENARIO_H5P_ID = 2;
	private const BRANCHING_SCENARIO_H5P_TITLE = 'Braking and turn-in decision path';
	private const INTERACTIVE_VIDEO_H5P_ID = 3;
	private const INTERACTIVE_VIDEO_H5P_TITLE = 'Lesson 1.1 coaching overlays';
	private const INTERACTIVE_VIDEO_MP4_FILE = 'videos/lesson-1-1-coaching-480p.mp4';
	private const INTERACTIVE_VIDEO_WEBM_FILE = 'videos/lesson-1-1-coaching-480p.webm';
	private const INTERACTIVE_VIDEO_CHECKPOINTS = [
		8  => 'Mid-corner oversteer',
		14 => 'Rear steps out again',
	];
	// The branching scenario now lives in its own "Driving Technique" module lesson,
	// not Lesson 1, after the Week 4 Day 3 driver/parent course split.
	private const BRANCHING_SCENARIO_LESSON_ID = 132;

	private const WEEK_4_CONTENT_TYPES = [
		'H5P.InteractiveVideo'  => 'Interactive Video',
		'H5P.BranchingScenario' => 'Branching Scenario',
		'H5P.DragQuestion'      => 'Drag and Drop',
	];

	public function __invoke(): void {
		$rows = [
			$this->plugin_check(),
			$this->hub_cache_check(),
		];

		foreach ( self::WEEK_4_CONTENT_TYPES as $machine_name => $label ) {
			$rows[] = $this->library_check( $label, $machine_name );
		}

		$rows[] = $this->lesson_one_content_check();
		$rows[] = $this->lesson_one_embed_check();
		$rows[] = $this->shortcode_render_check();
		$rows[] = $this->branching_scenario_content_check();
		$rows[] = $this->branching_scenario_embed_check();
		$rows[] = $this->branching_scenario_render_check();
		$rows[] = $this->interactive_video_content_check();
		$rows[] = $this->interactive_video_source_check();
		$rows[] = $this->interactive_video_popup_check();
		$rows[] = $this->interactive_video_fullscreen_check();
		$rows[] = $this->interactive_video_live_link_assets_check();
		$rows[] = $this->interactive_video_embed_check();
		$rows[] = $this->interactive_video_render_check();

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'check', 'status', 'detail' ] );
	}

	private function plugin_check(): array {
		$active_plugins = (array) get_option( 'active_plugins', [] );
		$status         = in_array( 'h5p/h5p.php', $active_plugins, true ) ? 'ok' : 'fail';

		return [
			'check'  => 'H5P plugin active',
			'status' => $status,
			'detail' => 'ok' === $status ? 'h5p/h5p.php' : 'H5P is not active.',
		];
	}

	private function hub_cache_check(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'h5p_libraries_hub_cache';
		if ( ! $this->table_exists( $table ) ) {
			return [
				'check'  => 'H5P hub cache',
				'status' => 'fail',
				'detail' => 'H5P hub cache table is missing.',
			];
		}

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count > 0 ) {
			return [
				'check'  => 'H5P hub cache',
				'status' => 'ok',
				'detail' => sprintf( '%d content type(s) cached from the H5P hub.', $count ),
			];
		}

		return [
			'check'  => 'H5P hub cache',
			'status' => 'pending',
			'detail' => 'Update the H5P content type cache.',
		];
	}

	private function library_check( string $label, string $machine_name ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'h5p_libraries';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT title, major_version, minor_version, patch_version
				 FROM {$table}
				 WHERE name = %s AND runnable = 1
				 ORDER BY major_version DESC, minor_version DESC, patch_version DESC
				 LIMIT 1",
				$machine_name
			)
		);

		if ( $row ) {
			return [
				'check'  => $label,
				'status' => 'ok',
				'detail' => sprintf( '%s %d.%d.%d installed.', $row->title, $row->major_version, $row->minor_version, $row->patch_version ),
			];
		}

		return [
			'check'  => $label,
			'status' => 'pending',
			'detail' => sprintf( 'Install %s from the H5P hub.', $machine_name ),
		];
	}

	private function lesson_one_content_check(): array {
		return $this->h5p_content_check(
			'Lesson 1 H5P content',
			self::LESSON_ONE_H5P_ID,
			self::LESSON_ONE_H5P_TITLE,
			'Create the first Lesson 1 H5P interaction.'
		);
	}

	private function h5p_content_check( string $label, int $content_id, string $expected_title, string $missing_detail ): array {
		global $wpdb;

		$content_table = $wpdb->prefix . 'h5p_contents';
		$library_table = $wpdb->prefix . 'h5p_libraries';
		$row           = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT hc.id, hc.title, hl.title AS library_title, hl.name
				 FROM {$content_table} hc
				 JOIN {$library_table} hl ON hc.library_id = hl.id
				 WHERE hc.id = %d",
				$content_id
			)
		);

		if ( $row && $expected_title === $row->title ) {
			return [
				'check'  => $label,
				'status' => 'ok',
				'detail' => sprintf( '#%d %s using %s.', $row->id, $row->title, $row->library_title ),
			];
		}

		return [
			'check'  => $label,
			'status' => 'pending',
			'detail' => $missing_detail,
		];
	}

	private function lesson_one_embed_check(): array {
		return $this->lesson_embed_check(
			'Lesson 1 checklist embed',
			self::LESSON_ONE_ID,
			self::LESSON_ONE_H5P_ID,
			'Embed the Lesson 1 footage checklist.'
		);
	}

	private function lesson_embed_check( string $label, int $lesson_id, int $content_id, string $missing_detail ): array {
		$content = (string) get_post_field( 'post_content', $lesson_id );
		$needle  = sprintf( '[h5p id="%d"]', $content_id );

		if ( str_contains( $content, $needle ) ) {
			return [
				'check'  => $label,
				'status' => 'ok',
				'detail' => sprintf( 'Lesson #%d contains %s.', $lesson_id, $needle ),
			];
		}

		return [
			'check'  => $label,
			'status' => 'pending',
			'detail' => sprintf( '%s Expected %s in lesson #%d.', $missing_detail, $needle, $lesson_id ),
		];
	}

	private function shortcode_render_check(): array {
		return $this->h5p_shortcode_render_check(
			'Lesson 1 checklist render',
			self::LESSON_ONE_H5P_ID,
			self::LESSON_ONE_H5P_TITLE
		);
	}

	private function h5p_shortcode_render_check( string $label, int $content_id, string $expected_title ): array {
		$html = do_shortcode( sprintf( '[h5p id="%d"]', $content_id ) );

		$has_expected_content = str_contains( $html, $expected_title )
			|| str_contains( $html, sprintf( 'data-content-id="%d"', $content_id ) );

		if ( str_contains( $html, 'h5p-iframe' ) && $has_expected_content ) {
			return [
				'check'  => $label,
				'status' => 'ok',
				'detail' => 'Shortcode renders the H5P iframe wrapper.',
			];
		}

		return [
			'check'  => $label,
			'status' => 'fail',
			'detail' => 'Shortcode did not render the expected H5P iframe wrapper.',
		];
	}

	private function branching_scenario_content_check(): array {
		return $this->h5p_content_check(
			'Branching scenario content',
			self::BRANCHING_SCENARIO_H5P_ID,
			self::BRANCHING_SCENARIO_H5P_TITLE,
			'Create the braking/turn-in branching scenario.'
		);
	}

	private function branching_scenario_embed_check(): array {
		return $this->lesson_embed_check(
			'Branching scenario embed',
			self::BRANCHING_SCENARIO_LESSON_ID,
			self::BRANCHING_SCENARIO_H5P_ID,
			'Embed the braking/turn-in branching scenario.'
		);
	}

	private function branching_scenario_render_check(): array {
		return $this->h5p_shortcode_render_check(
			'Branching scenario render',
			self::BRANCHING_SCENARIO_H5P_ID,
			self::BRANCHING_SCENARIO_H5P_TITLE
		);
	}

	private function interactive_video_content_check(): array {
		return $this->h5p_content_check(
			'Interactive video overlay',
			self::INTERACTIVE_VIDEO_H5P_ID,
			self::INTERACTIVE_VIDEO_H5P_TITLE,
			'Create the Lesson 1.1 Interactive Video overlay.'
		);
	}

	private function interactive_video_embed_check(): array {
		return $this->lesson_embed_check(
			'Interactive video embed',
			self::LESSON_ONE_ID,
			self::INTERACTIVE_VIDEO_H5P_ID,
			'Embed the Interactive Video in Lesson 1.1.'
		);
	}

	private function interactive_video_source_check(): array {
		global $wpdb;

		$parameters = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT parameters FROM {$wpdb->prefix}h5p_contents WHERE id = %d",
				self::INTERACTIVE_VIDEO_H5P_ID
			)
		);
		$params     = json_decode( (string) $parameters, true );
		$sources     = $params['interactiveVideo']['video']['files'] ?? [];
		$source_paths = is_array( $sources ) ? wp_list_pluck( $sources, 'path' ) : [];
		$upload_dir   = wp_upload_dir()['basedir'] . '/h5p/content/' . self::INTERACTIVE_VIDEO_H5P_ID . '/';
		$has_webm     = in_array( self::INTERACTIVE_VIDEO_WEBM_FILE, $source_paths, true )
			&& file_exists( $upload_dir . self::INTERACTIVE_VIDEO_WEBM_FILE );
		$has_mp4      = in_array( self::INTERACTIVE_VIDEO_MP4_FILE, $source_paths, true )
			&& file_exists( $upload_dir . self::INTERACTIVE_VIDEO_MP4_FILE );
		$is_local     = $has_webm && $has_mp4;

		return [
			'check'  => 'Interactive video browser-safe sources',
			'status' => $is_local ? 'ok' : 'fail',
			'detail' => $is_local
				? 'H5P ships local WebM and MP4 sources for codec fallback without CDN headers.'
				: 'Package both local WebM and MP4 H5P video sources for browser codec fallback.',
		];
	}

	private function interactive_video_popup_check(): array {
		global $wpdb;

		$filtered = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT filtered FROM {$wpdb->prefix}h5p_contents WHERE id = %d",
				self::INTERACTIVE_VIDEO_H5P_ID
			)
		);
		$params       = json_decode( (string) $filtered, true );
		$assets       = $params['interactiveVideo']['assets'] ?? [];
		$interactions = $assets['interactions'] ?? [];
		$bookmarks    = $assets['bookmarks'] ?? [];

		$files_exist = file_exists( WPMU_PLUGIN_DIR . '/p1-h5p-popup-controls.php' )
			&& file_exists( WPMU_PLUGIN_DIR . '/p1-h5p-popup-controls.js' )
			&& file_exists( WPMU_PLUGIN_DIR . '/p1-h5p-popup-controls.css' );
		$script      = $files_exist
			? file_get_contents( WPMU_PLUGIN_DIR . '/p1-h5p-popup-controls.js' )
			: '';
		$rewinds_on_click = str_contains( $script, 'bindRewindOnClick' )
			&& str_contains( $script, 'instance.seek(startTime, { force: true })' )
			&& str_contains( $script, 'shouldAutoOpen(instance, interactionButton)' );

		$configured_checkpoints = 0;
		foreach ( self::INTERACTIVE_VIDEO_CHECKPOINTS as $time => $title ) {
			$has_interaction = 0 < count(
				array_filter(
					$interactions,
					static fn ( array $interaction ): bool =>
						(float) ( $interaction['duration']['from'] ?? -1 ) === (float) $time
						&& true === ( $interaction['pause'] ?? false )
						&& 'button' === ( $interaction['displayType'] ?? '' )
						&& $title === ( $interaction['libraryTitle'] ?? '' )
				)
			);
			$has_bookmark = 0 < count(
				array_filter(
					$bookmarks,
					static fn ( array $bookmark ): bool =>
						(float) ( $bookmark['time'] ?? -1 ) === (float) $time
						&& $title === ( $bookmark['label'] ?? '' )
				)
			);

			if ( $has_interaction && $has_bookmark ) {
				++$configured_checkpoints;
			}
		}

		$checkpoints_ok = count( self::INTERACTIVE_VIDEO_CHECKPOINTS ) === $configured_checkpoints;
		$is_ok          = $files_exist && $checkpoints_ok && $rewinds_on_click;

		return [
			'check'  => 'Interactive video popup controls',
			'status' => $is_ok ? 'ok' : 'fail',
			'detail' => $is_ok
				? 'Two paused coaching checkpoints at 00:08 and 00:14 rewind on click and use the automatic popup and Continue control.'
				: 'Expected paused coaching checkpoints, rewind controls, or popup controls are missing.',
		];
	}

	private function interactive_video_fullscreen_check(): array {
		$css_file = WPMU_PLUGIN_DIR . '/p1-h5p-popup-controls.css';
		$js_file  = WPMU_PLUGIN_DIR . '/p1-h5p-popup-controls.js';
		$css      = file_exists( $css_file ) ? file_get_contents( $css_file ) : '';
		$js       = file_exists( $js_file ) ? file_get_contents( $js_file ) : '';

		$black_surround = str_contains( $css, '.h5p-container.h5p-semi-fullscreen' )
			&& str_contains( $css, '.h5p-container.h5p-fullscreen' )
			&& str_contains( $css, 'background-color: #000' );
		$fills_screen = str_contains( $js, 'maybeRefillFullscreen' )
			&& str_contains( $js, 'enterFullScreen' );
		$frosted_popup = str_contains( $css, 'backdrop-filter: blur' )
			&& str_contains( $css, '.h5p-dialog-wrapper .h5p-dialog' );
		$is_ok = $black_surround && $fills_screen && $frosted_popup;

		return [
			'check'  => 'Interactive video immersive fullscreen',
			'status' => $is_ok ? 'ok' : 'fail',
			'detail' => $is_ok
				? 'Fullscreen fills the screen (H5P resize re-run) with a black surround; compact dark frosted-glass popups still render.'
				: 'Fullscreen must re-run H5P resize, use a black surround, and keep the compact frosted-glass popup styling.',
		];
	}

	private function interactive_video_live_link_assets_check(): array {
		$plugin_file = WPMU_PLUGIN_DIR . '/p1-h5p-popup-controls.php';
		$source      = file_exists( $plugin_file ) ? file_get_contents( $plugin_file ) : '';
		$is_safe     = str_contains( $source, 'p1_h5p_popup_controls_asset_url' )
			&& str_contains( $source, "site_url( '/wp-content/mu-plugins/'" )
			&& ! str_contains( $source, "content_url( 'mu-plugins/p1-h5p-popup-controls" );

		return [
			'check'  => 'Interactive video Live Link assets',
			'status' => $is_safe ? 'ok' : 'fail',
			'detail' => $is_safe
				? 'Popup assets follow the active request host and work through Local Live Link.'
				: 'Use request-host-aware popup asset URLs so mobile Live Link embeds do not stay collapsed.',
		];
	}

	private function interactive_video_render_check(): array {
		return $this->h5p_shortcode_render_check(
			'Interactive video render',
			self::INTERACTIVE_VIDEO_H5P_ID,
			self::INTERACTIVE_VIDEO_H5P_TITLE
		);
	}

	private function table_exists( string $table ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}
}

WP_CLI::add_command( 'p1-h5p-readiness', 'P1_H5P_Readiness_Command' );
