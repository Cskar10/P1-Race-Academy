<?php
/**
 * Plugin Name: P1 Email Readiness
 * Description: WP-CLI launch-readiness audit for P1 Race Academy transactional email.
 * Version: 0.3.0
 * Author: P1 Race Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

final class P1_Email_Readiness_Command {
	public function __invoke(): void {
		$rows = [
			$this->plugin_check( 'FluentSMTP active', 'fluent-smtp/fluent-smtp.php' ),
			$this->plugin_check( 'WP Mail Logging active', 'wp-mail-logging/wp-mail-logging.php' ),
			$this->plugin_check( 'FluentCRM active', 'fluent-crm/fluent-crm.php' ),
			$this->fluent_smtp_connection_check(),
			$this->sender_identity_check(),
			$this->postmark_check(),
			$this->admin_email_tuning_check(),
			$this->dns_auth_check(),
			$this->gmail_inbox_test_check(),
			$this->welcome_sequence_check(),
		];

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'check', 'status', 'detail' ] );
	}

	private function plugin_check( string $label, string $plugin_file ): array {
		$active_plugins = (array) get_option( 'active_plugins', [] );
		$status         = in_array( $plugin_file, $active_plugins, true ) ? 'ok' : 'fail';

		return [
			'check'  => $label,
			'status' => $status,
			'detail' => 'ok' === $status ? $plugin_file : 'Plugin is not active.',
		];
	}

	private function fluent_smtp_connection_check(): array {
		$connection = $this->default_fluent_smtp_connection();
		if ( ! $connection ) {
			return [
				'check'  => 'FluentSMTP default connection',
				'status' => 'fail',
				'detail' => 'No default connection is configured.',
			];
		}

		return [
			'check'  => 'FluentSMTP default connection',
			'status' => 'ok',
			'detail' => sprintf(
				'provider=%s, sender=%s',
				(string) ( $connection['provider'] ?? '' ),
				(string) ( $connection['sender_email'] ?? '' )
			),
		];
	}

	private function sender_identity_check(): array {
		$connection    = $this->default_fluent_smtp_connection();
		$sender_name   = (string) ( $connection['sender_name'] ?? '' );
		$force_name    = (string) ( $connection['force_from_name'] ?? '' );
		$woo_from_name = (string) get_option( 'woocommerce_email_from_name' );

		if ( 'P1 Race Academy' === $sender_name && 'P1 Race Academy' === $woo_from_name && 'yes' === $force_name ) {
			return [
				'check'  => 'Sender identity',
				'status' => 'ok',
				'detail' => 'FluentSMTP and WooCommerce both use P1 Race Academy.',
			];
		}

		return [
			'check'  => 'Sender identity',
			'status' => 'warn',
			'detail' => sprintf(
				'FluentSMTP name=%s, force=%s, Woo name=%s',
				$sender_name ?: '(blank)',
				$force_name ?: '(blank)',
				$woo_from_name ?: '(blank)'
			),
		];
	}

	private function postmark_check(): array {
		$connection = $this->default_fluent_smtp_connection();
		$provider   = (string) ( $connection['provider'] ?? '' );
		$key_store  = (string) ( $connection['key_store'] ?? '' );
		$has_key    = ! empty( $connection['api_key'] ) || ( 'wp_config' === $key_store && defined( 'FLUENTMAIL_POSTMARK_API_KEY' ) );
		$sender     = strtolower( (string) ( $connection['sender_email'] ?? '' ) );

		if ( 'postmark' === $provider && $has_key ) {
			if ( str_ends_with( $sender, '@carpecool.fr' ) ) {
				return [
					'check'  => 'Postmark transport',
					'status' => 'pending',
					'detail' => 'Postmark is connected, but the current carpecool.fr alias sender already failed DMARC. Replace it with the owned sender domain.',
				];
			}

			return [
				'check'  => 'Postmark transport',
				'status' => 'ok',
				'detail' => 'Postmark provider configured; token presence verified without printing it.',
			];
		}

		return [
			'check'  => 'Postmark transport',
			'status' => 'pending',
			'detail' => 'Configure FluentSMTP provider=postmark with a verified sender/domain token before launch.',
		];
	}

	private function admin_email_tuning_check(): array {
		$sensei_enrolment_sends = apply_filters(
			'sensei_send_emails',
			true,
			(string) get_option( 'admin_email' ),
			'Test Sensei enrolment email',
			'',
			'student_starts_course',
			[]
		);

		$woo_new_order = null;
		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			$emails        = WC()->mailer()->get_emails();
			$woo_new_order = $emails['WC_Email_New_Order'] ?? null;
		}

		$woo_enabled = $woo_new_order && $woo_new_order->is_enabled();
		$is_tuned    = ! $sensei_enrolment_sends && $woo_enabled;

		return [
			'check'  => 'Admin sale email tuning',
			'status' => $is_tuned ? 'ok' : 'fail',
			'detail' => $is_tuned
				? 'WooCommerce New order stays enabled; duplicate Sensei Course Enrolled email is muted.'
				: sprintf(
					'WooCommerce New order enabled=%s; Sensei Course Enrolled sends=%s.',
					$woo_enabled ? 'yes' : 'no',
					$sensei_enrolment_sends ? 'yes' : 'no'
				),
		];
	}

	private function dns_auth_check(): array {
		return [
			'check'  => 'SPF/DKIM/DMARC',
			'status' => 'pending',
			'detail' => 'Requires the production sender domain and Postmark DNS records.',
		];
	}

	private function gmail_inbox_test_check(): array {
		return [
			'check'  => 'Real inbox test',
			'status' => 'pending',
			'detail' => 'Send a production transactional test to Gmail after Postmark and DNS are live.',
		];
	}

	private function welcome_sequence_check(): array {
		global $wpdb;

		$funnels_table   = $wpdb->prefix . 'fc_funnels';
		$sequences_table = $wpdb->prefix . 'fc_funnel_sequences';
		$campaigns_table = $wpdb->prefix . 'fc_campaigns';

		foreach ( [ $funnels_table, $sequences_table, $campaigns_table ] as $table ) {
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $found ) {
				continue;
			}

			return [
				'check'  => 'Purchase welcome sequence',
				'status' => 'pending',
				'detail' => 'One or more FluentCRM automation tables are missing.',
			];
		}

		$funnel_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$funnels_table} WHERE trigger_name = %s AND status = 'published' LIMIT 1",
				'p1_paid_course_order'
			)
		);

		if ( ! $funnel_id ) {
			return [
				'check'  => 'Purchase welcome sequence',
				'status' => 'pending',
				'detail' => 'Published P1 paid-order funnel not found. Run `wp p1-welcome-flow setup`.',
			];
		}

		$sequence_settings = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT settings FROM {$sequences_table} WHERE funnel_id = %d AND action_name = 'send_custom_email' AND status = 'published' LIMIT 1",
				$funnel_id
			)
		);
		$sequence_settings = maybe_unserialize( $sequence_settings );
		$campaign_id       = absint( $sequence_settings['reference_campaign'] ?? 0 );

		if ( ! $campaign_id ) {
			return [
				'check'  => 'Purchase welcome sequence',
				'status' => 'pending',
				'detail' => 'Published paid-order funnel has no welcome email action.',
			];
		}

		$email_body = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT email_body FROM {$campaigns_table} WHERE id = %d AND status = 'published' LIMIT 1",
				$campaign_id
			)
		);
		$has_preferences_link = str_contains( $email_body, '##crm.manage_subscription_url##' )
			|| str_contains( $email_body, '##crm.unsubscribe_url##' );

		return [
			'check'  => 'Purchase welcome sequence',
			'status' => $has_preferences_link ? 'ok' : 'review',
			'detail' => $has_preferences_link
				? sprintf( 'Published paid-order funnel #%d and welcome campaign #%d include a preferences link.', $funnel_id, $campaign_id )
				: 'Welcome campaign exists but needs an unsubscribe/preferences link.',
		];
	}

	private function default_fluent_smtp_connection(): array {
		$settings = get_option( 'fluentmail-settings', [] );
		if ( ! is_array( $settings ) ) {
			return [];
		}

		$key = (string) ( $settings['misc']['default_connection'] ?? '' );
		if ( $key && isset( $settings['connections'][ $key ]['provider_settings'] ) ) {
			return (array) $settings['connections'][ $key ]['provider_settings'];
		}

		$first = reset( $settings['connections'] );
		return is_array( $first ) ? (array) ( $first['provider_settings'] ?? [] ) : [];
	}
}

WP_CLI::add_command( 'p1-email-readiness', 'P1_Email_Readiness_Command' );
