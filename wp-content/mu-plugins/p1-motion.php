<?php
/**
 * Plugin Name: P1 Motion Layer (Tier 1)
 * Description: Tasteful motion layer: Lenis smooth scroll, scroll reveals, a charcoal hero with soft blue glow, scroll kart, and magnetic CTAs. Reduced-motion aware. Loaded on the front page and the Online Coaching page. Delete this file + the p1-motion/ folder to fully remove.
 * Version: 0.4.0
 * Author: P1 Race Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_enqueue_scripts',
	function () {
		// Front page + Online Coaching page only: keep smooth-scroll/effects off checkout, account, and course pages.
		if ( is_admin() || ! ( is_front_page() || is_page( 'online-coaching' ) ) ) {
			return;
		}

		$dir_url  = plugin_dir_url( __FILE__ ) . 'p1-motion/';
		$dir_path = plugin_dir_path( __FILE__ ) . 'p1-motion/';

		$css = $dir_path . 'p1-motion.css';
		$js  = $dir_path . 'p1-motion.js';

		wp_enqueue_style(
			'p1-motion',
			$dir_url . 'p1-motion.css',
			array(),
			file_exists( $css ) ? (string) filemtime( $css ) : '0.1.0'
		);

		wp_enqueue_script(
			'p1-lenis',
			$dir_url . 'lenis.min.js',
			array(),
			'1.1.14',
			true
		);

		wp_enqueue_script(
			'p1-motion',
			$dir_url . 'p1-motion.js',
			array( 'p1-lenis' ),
			file_exists( $js ) ? (string) filemtime( $js ) : '0.1.0',
			true
		);
	},
	20
);
