<?php
/**
 * Plugin Name: P1 Accessibility Polish
 * Description: Applies site-wide contrast, focus, touch-target, and generated-markup accessibility fixes.
 * Version: 0.1.0
 * Author: P1 Race Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Print late-loading styles so they can safely override theme and block styles.
 */
function p1_accessibility_styles(): void {
	?>
	<style id="p1-accessibility-polish">
		:root {
			--p1-action: #0c5e80;
			--p1-action-hover: #06415a;
			--p1-focus: #f5a524;
		}

		body .wp-block-button__link,
		body button:not(.menu-toggle-open):not(.menu-toggle-close),
		body .button,
		body input[type="submit"],
		body .wc-block-components-checkout-place-order-button {
			background-color: var(--p1-action) !important;
			color: #ffffff !important;
		}

		body .wp-block-button__link:hover,
		body .wp-block-button__link:focus-visible,
		body button:not(.menu-toggle-open):not(.menu-toggle-close):hover,
		body button:not(.menu-toggle-open):not(.menu-toggle-close):focus-visible,
		body .button:hover,
		body .button:focus-visible,
		body input[type="submit"]:hover,
		body input[type="submit"]:focus-visible,
		body .wc-block-components-checkout-place-order-button:hover,
		body .wc-block-components-checkout-place-order-button:focus-visible {
			background-color: var(--p1-action-hover) !important;
			color: #ffffff !important;
		}

		.entry-content a:not(.wp-block-button__link),
		.woocommerce a:not(.button),
		.wc-block-checkout a,
		.p1-checkout-account-note a {
			color: var(--p1-action);
		}

		.entry-content a:not(.wp-block-button__link):hover,
		.woocommerce a:not(.button):hover,
		.wc-block-checkout a:hover,
		.p1-checkout-account-note a:hover {
			color: var(--p1-action-hover);
		}

		/* Sensei course navigation: the module header <h3> carries Sensei's own
		   dark-teal color, which is invisible on the P1-blue button background
		   applied by the blanket button rule above. Force readable text. */
		body .sensei-lms-course-navigation-module__button .sensei-lms-course-navigation-module__title,
		body .sensei-lms-course-navigation-module__button svg {
			color: #ffffff !important;
			fill: currentColor;
		}

		/* Sensei learning-mode sidebar: centre the module heading text in its bar.
		   The title shares a flex button with the collapse chevron, so centring
		   the title alone would leave it optically left of centre. Pin the
		   chevron out of flow at the right edge and let the title span the bar. */
		body .sensei-lms-course-navigation-module__button {
			position: relative;
		}
		body .sensei-lms-course-navigation-module__button .sensei-lms-course-navigation-module__title {
			flex: 1 1 auto;
			padding-inline: 36px;
			text-align: center !important;
		}
		body .sensei-lms-course-navigation-module__button svg {
			position: absolute;
			inset-inline-end: 12px;
			inset-block: 0;
			margin-block: auto;
		}

		/* Sensei course outline (course page): vertically center the module title
		   and the collapse arrow inside the dark header bar. The title's inner
		   link adds ghost height, and the arrow button has no align-items, which
		   pushes its svg 12px above the button box. */
		body .wp-block-sensei-lms-course-outline-module__header {
			align-items: center !important;
		}
		body .wp-block-sensei-lms-course-outline-module__title,
		body .wp-block-sensei-lms-course-outline-module__title a {
			display: flex;
			align-items: center;
			line-height: 1.3;
		}
		body .wp-block-sensei-lms-course-outline__arrow {
			display: flex !important;
			align-items: center !important;
			justify-content: center !important;
		}
		body .wp-block-sensei-lms-course-outline__arrow svg {
			display: block;
		}

		.entry-content [style*="background-color:#1E96C9"],
		.entry-content [style*="background-color: #1E96C9"] {
			background-color: var(--p1-action) !important;
		}

		a:focus-visible,
		button:focus-visible,
		input:focus-visible,
		select:focus-visible,
		textarea:focus-visible,
		[tabindex]:not([tabindex="-1"]):focus-visible {
			outline: 3px solid var(--p1-focus) !important;
			outline-offset: 3px !important;
		}

		.wp-block-button__link,
		.button,
		button[type="submit"],
		input[type="submit"] {
			min-height: 44px;
		}

		input[type="checkbox"],
		input[type="radio"] {
			min-width: 24px;
			min-height: 24px;
		}

		.show-password-input {
			min-width: 44px;
			min-height: 44px;
		}

		.wp-block-sensei-lms-course-outline-module__header {
			min-height: 52px;
		}

		.wp-block-sensei-lms-course-outline-module__title a {
			display: flex;
			align-items: center;
			min-height: 44px;
		}

		.wp-block-sensei-lms-course-outline__arrow.sensei-collapsible__toggle {
			min-width: 44px;
			min-height: 44px;
		}

		@media (max-width: 767px) {
			.mobile-toggle-open-container .menu-toggle-open,
			#mobile-drawer .menu-toggle-close {
				min-width: 44px;
				min-height: 44px;
			}

			.site-footer a {
				display: inline-flex;
				align-items: center;
				min-height: 44px;
				padding-inline: 3px;
			}

			.woocommerce form input:not([type="checkbox"]):not([type="radio"]),
			.woocommerce form select,
			.woocommerce form textarea {
				min-height: 44px;
			}

			.entry-content p a:not(.wp-block-button__link),
			.entry-content li a:not(.wp-block-button__link),
			.woocommerce-account .woocommerce a:not(.button) {
				display: inline-flex;
				align-items: center;
				min-height: 24px;
			}

			.wc-block-checkout a,
			.wc-block-checkout button,
			.wc-block-checkout [role="button"] {
				display: inline-flex;
				align-items: center;
				min-height: 44px;
			}

			.wc-block-checkout input:not([type="checkbox"]):not([type="radio"]),
			.wc-block-checkout select,
			.wc-block-checkout textarea {
				min-height: 44px !important;
			}

			.wp-block-sensei-lms-course-list select,
			.wp-block-sensei-lms-course-list a {
				display: flex;
				align-items: center;
				min-height: 44px;
			}

			.sensei-course-theme__sidebar a,
			.sensei-course-theme__sidebar button {
				min-height: 44px;
			}

			.sensei-course-theme .custom-logo-link,
			.sensei-course-theme__header a,
			.sensei-course-theme__header button,
			.sensei-course-theme__sidebar-toggle,
			.sensei-lms-course-navigation-lesson__extra {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				min-width: 44px;
				min-height: 44px;
			}
		}
	</style>
	<?php
}
add_action( 'wp_head', 'p1_accessibility_styles', 999 );

/**
 * Repair accessibility defects in generated WooCommerce and Sensei markup.
 *
 * These blocks can render or rerender after DOMContentLoaded, so the repairs
 * also run from a throttled MutationObserver.
 */
function p1_accessibility_runtime_repairs(): void {
	?>
	<script id="p1-accessibility-runtime-repairs">
		(() => {
			let scheduled = false;

			const repair = () => {
				scheduled = false;

				document.querySelectorAll(
					'.wc-block-components-order-summary[aria-label], .wc-block-components-skeleton__element[aria-label]'
				).forEach((element) => {
					if (!element.hasAttribute('role')) {
						element.setAttribute('role', 'status');
					}
				});

				const checkoutEmail = document.querySelector('#email[autocomplete]');
				if (checkoutEmail && checkoutEmail.getAttribute('autocomplete') !== 'section-contact email') {
					checkoutEmail.setAttribute('autocomplete', 'section-contact email');
				}

				document.querySelectorAll('select[data-param-key*="course-filter"]').forEach((select) => {
					if (!select.hasAttribute('aria-label')) {
						select.setAttribute('aria-label', 'Filter courses by status');
					}
				});

				document.querySelectorAll('.sensei-progress-bar__bar[role="progressbar"]').forEach((progress) => {
					const value = progress.getAttribute('aria-valuenow') || '0';
					const label = `Course progress: ${value}%`;
					if (progress.getAttribute('aria-label') !== label) {
						progress.setAttribute('aria-label', label);
					}
				});

				document.querySelectorAll(
					'button.sensei-collapsible__toggle .screen-reader-text a'
				).forEach((link) => {
					link.replaceWith(document.createTextNode(link.textContent || 'Toggle module'));
				});
			};

			const scheduleRepair = () => {
				if (scheduled) {
					return;
				}
				scheduled = true;
				window.requestAnimationFrame(repair);
			};

			repair();
			new MutationObserver(scheduleRepair).observe(document.documentElement, {
				attributes: true,
				childList: true,
				subtree: true,
			});
		})();
	</script>
	<?php
}
add_action( 'wp_footer', 'p1_accessibility_runtime_repairs', 999 );
