<?php
/**
 * Plugin Name: P1 Purchase Welcome Flow
 * Description: Bridges paid P1 WooCommerce orders into a transactional FluentCRM welcome automation.
 * Version: 0.2.0
 * Author: P1 Race Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class P1_Purchase_Welcome_Flow {
	public const TRIGGER_NAME = 'p1_paid_course_order';
	public const FUNNEL_TITLE = 'P1 Course Purchase Welcome';
	public const CAMPAIGN_TITLE = 'P1 Course Purchase Welcome Email';

	private const ORDER_STARTED_META = '_p1_welcome_flow_started';

	private const PRODUCT_TIERS = [
		82  => [
			'slug' => 'full-access',
			'tag'  => 'P1 Customer: Full Access',
		],
		129 => [
			'slug' => 'driver',
			'tag'  => 'P1 Customer: Driver',
		],
		130 => [
			'slug' => 'parent',
			'tag'  => 'P1 Customer: Parent',
		],
	];

	public static function init(): void {
		add_filter( 'fluentcrm_funnel_triggers', [ __CLASS__, 'register_fluentcrm_trigger' ] );
		add_filter( 'fluentcrm_funnel_editor_details_' . self::TRIGGER_NAME, [ __CLASS__, 'prepare_trigger_editor' ] );

		add_action( 'woocommerce_payment_complete', [ __CLASS__, 'start_for_paid_order' ], 20 );
		add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'start_for_paid_order' ], 20 );
		add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'start_for_paid_order' ], 20 );
	}

	public static function register_fluentcrm_trigger( array $triggers ): array {
		$triggers[ self::TRIGGER_NAME ] = [
			'category'    => 'P1 Race Academy',
			'label'       => 'Paid P1 course order',
			'description' => 'Starts once when a paid WooCommerce order contains a P1 course product.',
			'icon'        => 'fc-icon-shopping-cart',
		];

		return $triggers;
	}

	public static function prepare_trigger_editor( $funnel ) {
		$funnel->settingsFields = [
			'title'     => 'Paid P1 course order',
			'sub_title' => 'Managed by the P1 Purchase Welcome Flow MU plugin.',
			'fields'    => [],
		];
		$funnel->conditionFields = [];

		return $funnel;
	}

	public static function start_for_paid_order( int $order_id ): void {
		if (
			! function_exists( 'wc_get_order' )
			|| ! class_exists( '\FluentCrm\App\Models\Funnel' )
			|| ! function_exists( 'FluentCrmApi' )
		) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( self::ORDER_STARTED_META, true ) ) {
			return;
		}

		if ( ! $order->is_paid() && ! in_array( $order->get_status(), [ 'processing', 'completed' ], true ) ) {
			return;
		}

		$tiers = self::get_order_tiers( $order );
		if ( ! $tiers ) {
			return;
		}

		$email = sanitize_email( $order->get_billing_email() );
		if ( ! $email ) {
			$order->add_order_note( 'P1 welcome flow: no valid billing email, so the welcome automation was not started.' );
			return;
		}

		try {
			$subscriber = self::create_or_update_transactional_contact( $order, $email );
			if ( ! $subscriber ) {
				$existing = \FluentCrm\App\Models\Subscriber::where( 'email', $email )->first();
				$order->update_meta_data(
					self::ORDER_STARTED_META,
					[
						'finished_at' => current_time( 'mysql' ),
						'status'      => 'skipped_contact_status',
						'contact_status' => $existing ? (string) $existing->status : 'unknown',
						'tiers'       => array_values( $tiers ),
					]
				);
				$order->save();
				return;
			}

			$tag_ids = self::get_tier_tag_ids( $tiers );
			if ( $tag_ids ) {
				$subscriber->attachTags( $tag_ids );
			}

			$parts = self::get_published_flow_parts();
			if ( ! $parts ) {
				$order->add_order_note( 'P1 welcome flow: the published FluentCRM welcome automation is missing. Run `wp p1-welcome-flow setup`.' );
				return;
			}

			$before_email_ids = \FluentCrm\App\Models\CampaignEmail::where( 'campaign_id', $parts['campaign']->id )
				->where( 'subscriber_id', $subscriber->id )
				->pluck( 'id' )
				->toArray();

			$funnel_subscriber = \FluentCrm\App\Models\FunnelSubscriber::where( 'funnel_id', $parts['funnel']->id )
				->where( 'subscriber_id', $subscriber->id )
				->first();
			$delivery_mode     = 'funnel';

			if ( $funnel_subscriber ) {
				$email_ids     = self::create_repeat_purchase_email_job( $parts['campaign'], $subscriber, $order_id );
				$delivery_mode = 'repeat_purchase_campaign';
			} else {
				( new \FluentCrm\App\Services\Funnel\FunnelProcessor() )->startFunnelSequence(
					$parts['funnel'],
					[
						'email'      => $subscriber->email,
						'first_name' => $subscriber->first_name,
						'last_name'  => $subscriber->last_name,
						'user_id'    => $subscriber->user_id,
						'status'     => $subscriber->status,
					],
					[
						'source_trigger_name' => self::TRIGGER_NAME,
						'source_ref_id'       => $order_id,
					],
					$subscriber
				);

				$email_ids = \FluentCrm\App\Models\CampaignEmail::where( 'campaign_id', $parts['campaign']->id )
					->where( 'subscriber_id', $subscriber->id )
					->whereNotIn( 'id', $before_email_ids ?: [ 0 ] )
					->pluck( 'id' )
					->toArray();
			}

			if ( count( $email_ids ) !== 1 ) {
				throw new RuntimeException( 'FluentCRM did not create exactly one welcome email job for this order.' );
			}

			$order->update_meta_data(
				self::ORDER_STARTED_META,
				[
					'started_at'    => current_time( 'mysql' ),
					'status'        => 'email_job_created',
					'funnel_id'     => (int) $parts['funnel']->id,
					'campaign_id'   => (int) $parts['campaign']->id,
					'email_job_id'  => (int) $email_ids[0],
					'subscriber_id' => (int) $subscriber->id,
					'tiers'         => array_values( $tiers ),
					'delivery_mode' => $delivery_mode,
				]
			);
			$order->add_order_note(
				sprintf(
					'P1 welcome flow: created FluentCRM welcome email job #%d for %s via %s. Tier tags: %s.',
					(int) $email_ids[0],
					$email,
					$delivery_mode,
					implode( ', ', $tiers )
				)
			);
			$order->save();
		} catch ( Throwable $error ) {
			$order->add_order_note( 'P1 welcome flow error: ' . sanitize_text_field( $error->getMessage() ) );
			$order->save();
			error_log( 'P1 welcome flow order #' . $order_id . ': ' . $error->getMessage() );
		}
	}

	public static function setup(): array {
		if (
			! class_exists( '\FluentCrm\App\Models\Funnel' )
			|| ! class_exists( '\FluentCrm\App\Models\FunnelCampaign' )
			|| ! class_exists( '\FluentCrm\App\Models\FunnelSequence' )
			|| ! class_exists( '\FluentCrm\App\Models\Tag' )
		) {
			throw new RuntimeException( 'FluentCRM is not active.' );
		}

		$tag_ids = [];
		foreach ( self::PRODUCT_TIERS as $tier ) {
			$slug = sanitize_title( $tier['tag'] );
			$tag  = \FluentCrm\App\Models\Tag::where( 'slug', $slug )->first();

			if ( ! $tag ) {
				$tag = \FluentCrm\App\Models\Tag::create(
					[
						'title'       => $tier['tag'],
						'slug'        => $slug,
						'description' => 'Applied automatically after a paid P1 course order.',
					]
				);
			}

			$tag_ids[ $tier['slug'] ] = (int) $tag->id;
		}

		$funnel = \FluentCrm\App\Models\Funnel::where( 'trigger_name', self::TRIGGER_NAME )->first();
		if ( ! $funnel ) {
			$funnel = new \FluentCrm\App\Models\Funnel();
		}

		$funnel->title        = self::FUNNEL_TITLE;
		$funnel->trigger_name = self::TRIGGER_NAME;
		$funnel->status       = 'published';
		$funnel->conditions   = [];
		$funnel->settings     = [
			'__force_run_actions' => 'yes',
		];
		$funnel->created_by   = get_current_user_id();
		$funnel->save();

		$campaign = \FluentCrm\App\Models\FunnelCampaign::where( 'parent_id', $funnel->id )
			->where( 'title', self::CAMPAIGN_TITLE )
			->first();

		if ( ! $campaign ) {
			$campaign = new \FluentCrm\App\Models\FunnelCampaign();
		}

		$campaign->parent_id        = $funnel->id;
		$campaign->title            = self::CAMPAIGN_TITLE;
		$campaign->status           = 'published';
		$campaign->email_subject    = 'Your P1 Race Academy course access is ready';
		$campaign->email_pre_header = 'Your purchase is confirmed and your course access is ready.';
		$campaign->email_body       = self::get_email_body();
		$campaign->design_template  = 'raw_html';
		$campaign->settings         = [
			'is_transactional' => 'yes',
			'mailer_settings'  => [
				'from_name'      => '',
				'from_email'     => '',
				'reply_to_name'  => '',
				'reply_to_email' => '',
				'is_custom'      => 'no',
			],
			'footer_settings'  => [
				'disable_footer' => 'yes',
			],
			'template_config'  => [],
		];
		$campaign->save();

		$sequence = \FluentCrm\App\Models\FunnelSequence::where( 'funnel_id', $funnel->id )
			->where( 'action_name', 'send_custom_email' )
			->first();

		if ( ! $sequence ) {
			$sequence = new \FluentCrm\App\Models\FunnelSequence();
		}

		$sequence->funnel_id      = $funnel->id;
		$sequence->action_name    = 'send_custom_email';
		$sequence->parent_id      = 0;
		$sequence->condition_type = '';
		$sequence->title          = 'Send purchase welcome email';
		$sequence->description    = 'Transactional welcome email after a paid P1 course purchase.';
		$sequence->status         = 'published';
		$sequence->conditions     = [];
		$sequence->settings       = [
			'reference_campaign' => (int) $campaign->id,
			'send_email_to_type' => 'contact',
			'is_scheduled'       => 'no',
			'scheduled_at'       => '',
			'skip_if_overdue'    => 'no',
			'mailer_settings'    => [
				'from_name'      => '',
				'from_email'     => '',
				'reply_to_name'  => '',
				'reply_to_email' => '',
				'is_custom'      => 'no',
			],
		];
		$sequence->delay           = 0;
		$sequence->c_delay         = 0;
		$sequence->sequence        = 1;
		$sequence->type            = 'action';
		$sequence->save();

		( new \FluentCrm\App\Hooks\Handlers\FunnelHandler() )->resetFunnelIndexes();

		return [
			'funnel_id'   => (int) $funnel->id,
			'campaign_id' => (int) $campaign->id,
			'sequence_id' => (int) $sequence->id,
			'tag_ids'     => $tag_ids,
		];
	}

	public static function get_status_rows(): array {
		$rows = [];
		$funnel = class_exists( '\FluentCrm\App\Models\Funnel' )
			? \FluentCrm\App\Models\Funnel::where( 'trigger_name', self::TRIGGER_NAME )->first()
			: null;

		$rows[] = [
			'component' => 'Published purchase funnel',
			'status'    => $funnel && 'published' === $funnel->status ? 'ok' : 'missing',
			'detail'    => $funnel ? sprintf( '#%d %s', $funnel->id, $funnel->title ) : 'Run `wp p1-welcome-flow setup`.',
		];

		$sequence = $funnel
			? \FluentCrm\App\Models\FunnelSequence::where( 'funnel_id', $funnel->id )
				->where( 'action_name', 'send_custom_email' )
				->where( 'status', 'published' )
				->first()
			: null;

		$rows[] = [
			'component' => 'Published welcome email action',
			'status'    => $sequence ? 'ok' : 'missing',
			'detail'    => $sequence ? sprintf( '#%d', $sequence->id ) : 'No published send_custom_email action.',
		];

		foreach ( self::PRODUCT_TIERS as $tier ) {
			$tag = class_exists( '\FluentCrm\App\Models\Tag' )
				? \FluentCrm\App\Models\Tag::where( 'slug', sanitize_title( $tier['tag'] ) )->first()
				: null;

			$rows[] = [
				'component' => $tier['tag'],
				'status'    => $tag ? 'ok' : 'missing',
				'detail'    => $tag ? sprintf( 'tag #%d', $tag->id ) : 'Tier tag missing.',
			];
		}

		return $rows;
	}

	private static function create_or_update_transactional_contact( WC_Order $order, string $email ) {
		$existing = \FluentCrm\App\Models\Subscriber::where( 'email', $email )->first();
		if ( $existing && in_array( $existing->status, [ 'bounced', 'complained', 'spammed' ], true ) ) {
			$order->add_order_note(
				sprintf(
					'P1 welcome flow: skipped FluentCRM welcome because contact status is "%s".',
					$existing->status
				)
			);
			return false;
		}

		$data = [
			'email'      => $email,
			'first_name' => sanitize_text_field( $order->get_billing_first_name() ),
			'last_name'  => sanitize_text_field( $order->get_billing_last_name() ),
			'user_id'    => absint( $order->get_user_id() ),
			'source'     => 'P1 WooCommerce purchase',
		];

		if ( ! $existing || 'subscribed' !== $existing->status ) {
			$data['status'] = 'transactional';
		}

		return FluentCrmApi( 'contacts' )->createOrUpdate( $data, true );
	}

	private static function get_order_tiers( WC_Order $order ): array {
		$tiers = [];

		foreach ( $order->get_items() as $item ) {
			$product_id = absint( $item->get_product_id() );
			if ( isset( self::PRODUCT_TIERS[ $product_id ] ) ) {
				$tiers[] = self::PRODUCT_TIERS[ $product_id ]['slug'];
			}
		}

		return array_values( array_unique( $tiers ) );
	}

	private static function get_tier_tag_ids( array $tiers ): array {
		$ids = [];

		foreach ( self::PRODUCT_TIERS as $tier ) {
			if ( ! in_array( $tier['slug'], $tiers, true ) ) {
				continue;
			}

			$tag = \FluentCrm\App\Models\Tag::where( 'slug', sanitize_title( $tier['tag'] ) )->first();
			if ( $tag ) {
				$ids[] = (int) $tag->id;
			}
		}

		return $ids;
	}

	private static function get_published_flow_parts(): array {
		$funnel = \FluentCrm\App\Models\Funnel::where( 'trigger_name', self::TRIGGER_NAME )
			->where( 'status', 'published' )
			->first();
		if ( ! $funnel ) {
			return [];
		}

		$sequence = \FluentCrm\App\Models\FunnelSequence::where( 'funnel_id', $funnel->id )
			->where( 'action_name', 'send_custom_email' )
			->where( 'status', 'published' )
			->first();
		if ( ! $sequence ) {
			return [];
		}

		$campaign_id = absint( $sequence->settings['reference_campaign'] ?? 0 );
		$campaign    = $campaign_id ? \FluentCrm\App\Models\FunnelCampaign::find( $campaign_id ) : null;
		if ( ! $campaign || 'published' !== $campaign->status ) {
			return [];
		}

		return [
			'funnel'   => $funnel,
			'sequence' => $sequence,
			'campaign' => $campaign,
		];
	}

	private static function create_repeat_purchase_email_job( $campaign, $subscriber, int $order_id ): array {
		$email_ids = $campaign->processAndSubscribe(
			$subscriber,
			[],
			[
				'status'       => 'scheduled',
				'scheduled_at' => current_time( 'mysql' ),
				'email_type'   => 'funnel_email_campaign',
				'note'         => sprintf( 'P1 repeat-purchase welcome for WooCommerce order #%d', $order_id ),
			]
		);

		if ( $email_ids ) {
			do_action( 'fluentcrm_process_contact_jobs', $subscriber );
		}

		return array_map( 'absint', (array) $email_ids );
	}

	private static function get_email_body(): string {
		return '<p>Hi {{contact.first_name}},</p>'
			. '<p>Your P1 Race Academy purchase is confirmed and your course access is ready.</p>'
			. '<p><a href="##wp.url##/my-account/">Open your account and start learning</a></p>'
			. '<p>If you have trouble accessing your course, reply to this email and we will help.</p>'
			. '<p>P1 Race Academy</p>'
			. '<p><small><a href="##crm.manage_subscription_url##">Manage email preferences</a></small></p>';
	}
}

P1_Purchase_Welcome_Flow::init();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	final class P1_Purchase_Welcome_Flow_CLI_Command {
		public function setup(): void {
			$result = P1_Purchase_Welcome_Flow::setup();

			WP_CLI::success(
				sprintf(
					'Purchase welcome flow ready: funnel #%d, campaign #%d, sequence #%d.',
					$result['funnel_id'],
					$result['campaign_id'],
					$result['sequence_id']
				)
			);
		}

		public function status(): void {
			\WP_CLI\Utils\format_items(
				'table',
				P1_Purchase_Welcome_Flow::get_status_rows(),
				[ 'component', 'status', 'detail' ]
			);
		}
	}

	WP_CLI::add_command( 'p1-welcome-flow', 'P1_Purchase_Welcome_Flow_CLI_Command' );
}
