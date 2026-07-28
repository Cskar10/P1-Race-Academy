<?php
/**
 * Plugin Name: P1 Branded 404 Page
 * Description: Replaces the default Kadence 404 content with a branded recovery page.
 * Version: 0.2.0
 * Author: P1 Race Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return a published page URL, falling back to a known path.
 */
function p1_404_page_url( string $slug, string $fallback ): string {
	$page = get_page_by_path( $slug );

	if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
		$url = get_permalink( $page );
		if ( $url ) {
			return $url;
		}
	}

	return home_url( $fallback );
}

/**
 * Replace Kadence's generic 404 content after the theme has registered its hook.
 */
function p1_replace_kadence_404_content(): void {
	if ( ! is_404() ) {
		return;
	}

	remove_action( 'kadence_404_content', 'Kadence\get_404_content' );
	add_action( 'kadence_404_content', 'p1_render_404_content' );
}
add_action( 'wp', 'p1_replace_kadence_404_content' );

/**
 * Render the branded recovery page.
 */
function p1_render_404_content(): void {
	$home_url      = home_url( '/' );
	$course_url    = p1_404_page_url( 'online-coaching', '/online-coaching/' );
	$dashboard_url = p1_404_page_url( 'my-courses', '/my-courses/' );
	$support_url   = p1_404_page_url( 'contact', '/contact/' );
	?>
	<section class="p1-404" aria-labelledby="p1-404-title">
		<div class="p1-404__card">
			<p class="p1-404__eyebrow">404 · Off track</p>
			<p class="p1-404__number" aria-hidden="true">404</p>
			<h1 id="p1-404-title">That page missed the apex.</h1>
			<p class="p1-404__intro">
				The link may be outdated or the page may have moved. Head back to the
				academy, explore the course, or open your student dashboard.
			</p>

			<div class="p1-404__actions">
				<a class="p1-404__button p1-404__button--primary" href="<?php echo esc_url( $home_url ); ?>">Back to home</a>
				<a class="p1-404__button p1-404__button--secondary" href="<?php echo esc_url( $course_url ); ?>">Explore the course</a>
			</div>

			<nav class="p1-404__links" aria-label="Helpful links">
				<a href="<?php echo esc_url( $dashboard_url ); ?>">Student dashboard</a>
				<a href="<?php echo esc_url( $support_url ); ?>">Get support</a>
			</nav>
		</div>
	</section>
	<?php
}

/**
 * Add 404-only styles without modifying the parent theme.
 */
function p1_enqueue_404_styles(): void {
	if ( ! is_404() ) {
		return;
	}

	wp_register_style( 'p1-404-page', false, [], '0.1.0' );
	wp_enqueue_style( 'p1-404-page' );
	wp_add_inline_style(
		'p1-404-page',
		'
		body.error404 .content-area {
			margin: 0;
		}

		body.error404 .content-container.site-container {
			max-width: none;
			padding: 0;
		}

		body.error404 .content-wrap {
			display: block;
		}

		.p1-404 {
			box-sizing: border-box;
			display: grid;
			min-height: calc(100vh - 220px);
			place-items: center;
			padding: clamp(48px, 9vw, 112px) 24px;
			background:
				linear-gradient(135deg, rgba(30, 150, 201, 0.16), transparent 45%),
				#101010;
			color: #ffffff;
			position: relative;
			overflow: hidden;
		}

		.p1-404::after {
			content: "";
			position: absolute;
			inset: auto 0 0;
			height: 10px;
			background: repeating-linear-gradient(
				90deg,
				#ffffff 0 20px,
				#3fb1e5 20px 40px,
				#0c5e80 40px 60px
			);
		}

		.p1-404__card {
			box-sizing: border-box;
			width: min(100%, 860px);
			padding: clamp(32px, 6vw, 64px);
			border: 1px solid rgba(187, 217, 230, 0.45);
			border-radius: 16px;
			background: rgba(21, 24, 27, 0.92);
			box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
			position: relative;
			z-index: 1;
		}

		.p1-404__eyebrow {
			margin: 0 0 8px;
			color: #74c9ed;
			font-size: 14px;
			font-weight: 800;
			letter-spacing: 0.12em;
			text-transform: uppercase;
		}

		.p1-404__number {
			margin: 0;
			color: rgba(63, 177, 229, 0.18);
			font-size: clamp(92px, 20vw, 180px);
			font-weight: 900;
			letter-spacing: -0.08em;
			line-height: 0.82;
		}

		.p1-404 h1 {
			max-width: 680px;
			margin: 20px 0 16px;
			color: #ffffff;
			font-size: clamp(36px, 6vw, 64px);
			line-height: 1.05;
		}

		.p1-404__intro {
			max-width: 650px;
			margin: 0;
			color: #dcecf3;
			font-size: clamp(17px, 2.3vw, 20px);
			line-height: 1.65;
		}

		.p1-404__actions {
			display: flex;
			flex-wrap: wrap;
			gap: 14px;
			margin-top: 32px;
		}

		.p1-404__button {
			box-sizing: border-box;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-height: 48px;
			padding: 12px 20px;
			border: 2px solid #0c5e80;
			border-radius: 6px;
			font-weight: 800;
			text-decoration: none;
		}

		.p1-404__button--primary {
			background: #0c5e80;
			color: #ffffff;
		}

		.p1-404__button--primary:hover,
		.p1-404__button--primary:focus-visible {
			border-color: #3fb1e5;
			background: #0c5e80;
			color: #ffffff;
		}

		.p1-404__button--secondary {
			background: transparent;
			color: #ffffff;
		}

		.p1-404__button--secondary:hover,
		.p1-404__button--secondary:focus-visible {
			background: #ffffff;
			color: #0c5e80;
		}

		.p1-404__button:focus-visible,
		.p1-404__links a:focus-visible {
			outline: 3px solid #ffffff;
			outline-offset: 4px;
		}

		.p1-404__links {
			display: flex;
			flex-wrap: wrap;
			gap: 12px 24px;
			margin-top: 28px;
			padding-top: 24px;
			border-top: 1px solid rgba(187, 217, 230, 0.35);
		}

		.p1-404__links a {
			color: #74c9ed;
			font-weight: 700;
			text-underline-offset: 4px;
		}

		.p1-404__links a:hover {
			color: #ffffff;
		}

		@media (max-width: 600px) {
			.p1-404 {
				padding: 32px 16px 42px;
			}

			.p1-404__card {
				padding: 28px 22px 32px;
				border-radius: 12px;
			}

			.p1-404__actions,
			.p1-404__button {
				width: 100%;
			}

			.p1-404__links {
				display: grid;
				gap: 14px;
			}

			.p1-404__links a {
				display: flex;
				align-items: center;
				min-height: 44px;
			}
		}
		'
	);
}
add_action( 'wp_enqueue_scripts', 'p1_enqueue_404_styles', 30 );
