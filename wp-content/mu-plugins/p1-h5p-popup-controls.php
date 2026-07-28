<?php
/**
 * Plugin Name: P1 H5P Popup Controls
 * Description: Adds automatic pause-description popups with an explicit continue control to P1 Interactive Video content.
 * Version: 0.2.3
 * Author: P1 Race Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function p1_h5p_popup_controls_asset_url( string $filename ): string {
	return site_url( '/wp-content/mu-plugins/' . $filename );
}

add_action(
	'h5p_alter_library_scripts',
	static function ( array &$scripts, array $libraries, string $embed_type ): void {
		if ( ! isset( $libraries['H5P.InteractiveVideo'] ) ) {
			return;
		}

		$scripts[] = (object) [
			'path'    => p1_h5p_popup_controls_asset_url( 'p1-h5p-popup-controls.js' ),
			'version' => '?ver=0.2.3',
		];
	},
	10,
	3
);

add_action(
	'h5p_alter_library_styles',
	static function ( array &$styles, array $libraries, string $embed_type ): void {
		if ( ! isset( $libraries['H5P.InteractiveVideo'] ) ) {
			return;
		}

		$styles[] = (object) [
			'path'    => p1_h5p_popup_controls_asset_url( 'p1-h5p-popup-controls.css' ),
			'version' => '?ver=0.2.3',
		];
	},
	10,
	3
);
