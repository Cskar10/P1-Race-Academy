<?php
/**
 * Plugin Name: P1 Video Submission Flow
 * Description: Local intake form for Online Driver Coaching video submissions. Uploaded footage is stored privately outside the web root and served only to logged-in coaching staff via an authenticated download handler.
 * Version: 0.6.0
 * Author: P1 Race Academy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class P1_Video_Submission_Flow {
	private const POST_TYPE  = 'p1_video_submission';
	private const PRODUCT_IDS = [ 82, 129 ];
	private const ACTION     = 'p1_video_submission';
	private const NONCE     = 'p1_video_submission_nonce';
	private const VIDEO_FILE_FIELD = 'video_file';
	private const DOWNLOAD_ACTION  = 'p1_download_submission_video';
	private const PRIVATE_DIRNAME  = 'p1-private-submissions';

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'register_post_type' ] );
		add_shortcode( 'p1_video_submission_form', [ __CLASS__, 'render_form' ] );
		add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'handle_submission' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION, [ __CLASS__, 'handle_submission' ] );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, [ __CLASS__, 'handle_download' ] );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, [ __CLASS__, 'register_meta_box' ] );
		add_action( 'woocommerce_thankyou', [ __CLASS__, 'render_order_received_next_step' ], 20 );
		add_filter( 'the_content', [ __CLASS__, 'append_order_received_next_step' ], 20 );
	}

	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'       => [
					'name'          => 'Video Submissions',
					'singular_name' => 'Video Submission',
					'menu_name'     => 'P1 Submissions',
				],
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'supports'     => [ 'title', 'editor', 'custom-fields' ],
				'menu_icon'    => 'dashicons-video-alt3',
			]
		);
	}

	public static function render_form(): string {
		$user        = wp_get_current_user();
		$parent_name = $user && $user->exists() ? $user->display_name : '';
		$email       = $user && $user->exists() ? $user->user_email : '';
		$status      = isset( $_GET['p1_submission_status'] ) ? sanitize_key( wp_unslash( $_GET['p1_submission_status'] ) ) : '';
		$error       = isset( $_GET['p1_submission_error'] ) ? sanitize_key( wp_unslash( $_GET['p1_submission_error'] ) ) : '';
		$max_upload  = size_format( wp_max_upload_size() );

		ob_start();
		?>
		<div id="p1-video-submission-form" class="p1-video-submission-flow">
			<?php if ( 'success' === $status ) : ?>
				<div class="p1-video-submission-flow__notice p1-video-submission-flow__notice--success">
					<strong>Submission received.</strong> P1 Race Academy now has the video details for review.
				</div>
			<?php elseif ( $error ) : ?>
				<div class="p1-video-submission-flow__notice p1-video-submission-flow__notice--error">
					<strong>Submission not sent.</strong> <?php echo esc_html( self::get_error_message( $error ) ); ?>
				</div>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<?php wp_nonce_field( self::ACTION, self::NONCE ); ?>

				<p class="p1-video-submission-flow__intro">Use this form after checkout to upload footage or send a video link with the context for the Online Driver Coaching review.</p>

				<label>
					<span>Parent / contact name *</span>
					<input type="text" name="parent_name" value="<?php echo esc_attr( $parent_name ); ?>" required>
				</label>

				<label>
					<span>Email *</span>
					<input type="email" name="email" value="<?php echo esc_attr( $email ); ?>" required>
				</label>

				<label>
					<span>Phone</span>
					<input type="tel" name="phone">
				</label>

				<label>
					<span>Driver name *</span>
					<input type="text" name="driver_name" required>
				</label>

				<label>
					<span>Driver age</span>
					<input type="text" name="driver_age">
				</label>

				<label>
					<span>Kart class / category</span>
					<input type="text" name="kart_class" placeholder="Example: Cadet 12, KA3 Junior">
				</label>

				<label>
					<span>Track / event</span>
					<input type="text" name="track_name" placeholder="Example: Todd Road practice, club race heat 2">
				</label>

				<label>
					<span>Upload video file</span>
					<input type="file" name="<?php echo esc_attr( self::VIDEO_FILE_FIELD ); ?>" accept="video/mp4,video/quicktime,video/x-msvideo,video/webm">
					<small>Optional if you provide a link below. Uploads are stored privately — only P1 Race Academy coaching staff can access them. Max upload size: <?php echo esc_html( $max_upload ); ?>. For large files, a share link is best.</small>
				</label>

				<label>
					<span>Video link</span>
					<input type="url" name="video_url" placeholder="Google Drive, Dropbox, YouTube unlisted, or similar">
					<small>Optional if you upload a file above. Make sure cloud links are viewable by P1 Race Academy.</small>
				</label>

				<label>
					<span>What should the review focus on? *</span>
					<textarea name="goals" rows="7" required placeholder="Tell us what the driver is working on, where time is being lost, and anything specific you want checked."></textarea>
				</label>

				<label>
					<span>Order number</span>
					<input type="text" name="order_number" placeholder="Optional, if you have it">
				</label>

				<label class="p1-video-submission-flow__checkbox">
					<input type="checkbox" name="consent" value="1" required>
					<span>I confirm I have permission to share this footage for coaching review. *</span>
				</label>

				<button type="submit" class="wp-element-button">Submit footage details</button>
			</form>
		</div>
		<style>
			.p1-video-submission-flow {
				max-width: 760px;
			}
			.p1-video-submission-flow form {
				display: grid;
				gap: 18px;
			}
			.p1-video-submission-flow label {
				display: grid;
				gap: 7px;
				font-weight: 700;
			}
			.p1-video-submission-flow input,
			.p1-video-submission-flow textarea {
				box-sizing: border-box;
				width: 100%;
				border: 1px solid #cbd5e1;
				border-radius: 4px;
				padding: 12px;
				font: inherit;
			}
			.p1-video-submission-flow small {
				color: #475569;
				font-weight: 400;
			}
			.p1-video-submission-flow__checkbox {
				display: flex !important;
				align-items: flex-start;
				gap: 10px !important;
			}
			.p1-video-submission-flow__checkbox input {
				width: auto;
				margin-top: 5px;
			}
			.p1-video-submission-flow__notice {
				border: 1px solid #cbd5e1;
				margin-bottom: 22px;
				padding: 14px 16px;
			}
			.p1-video-submission-flow__notice--success {
				background: #ecfdf3;
				border-color: #86efac;
			}
			.p1-video-submission-flow__notice--error {
				background: #fef2f2;
				border-color: #fca5a5;
			}
			.p1-video-submission-flow__intro {
				margin-top: 0;
			}
		</style>
		<?php

		return (string) ob_get_clean();
	}

	public static function handle_submission(): void {
		$referer = wp_get_referer() ?: home_url( '/contact/' );

		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::ACTION ) ) {
			self::redirect_with_error( $referer, 'nonce' );
		}

		$submission = self::sanitize_submission( wp_unslash( $_POST ) );
		$missing    = self::get_missing_required_fields( $submission );
		if ( $missing ) {
			self::redirect_with_error( $referer, 'missing' );
		}

		if ( ! is_email( $submission['email'] ) ) {
			self::redirect_with_error( $referer, 'email' );
		}

		$has_video_file = self::has_video_file_upload();

		if ( ! $has_video_file && empty( $submission['video_url'] ) ) {
			self::redirect_with_error( $referer, 'video_required' );
		}

		if ( ! empty( $submission['video_url'] ) && ! wp_http_validate_url( $submission['video_url'] ) ) {
			self::redirect_with_error( $referer, 'video_url' );
		}

		if ( $has_video_file ) {
			$upload_error = self::validate_video_upload();
			if ( $upload_error ) {
				self::redirect_with_error( $referer, $upload_error );
			}
		}

		$post_id = wp_insert_post(
			[
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'private',
				'post_title'   => sprintf(
					'Video submission - %s - %s',
					$submission['driver_name'],
					current_time( 'Y-m-d H:i' )
				),
				'post_content' => $submission['goals'],
				'post_author'  => get_current_user_id(),
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			self::redirect_with_error( $referer, 'save' );
		}

		foreach ( $submission as $key => $value ) {
			update_post_meta( $post_id, '_' . $key, $value );
		}

		update_post_meta( $post_id, '_submitted_user_id', get_current_user_id() );
		update_post_meta( $post_id, '_submitted_at', current_time( 'mysql' ) );

		if ( $has_video_file ) {
			$stored = self::store_video_upload_private( $post_id );
			if ( is_wp_error( $stored ) ) {
				wp_delete_post( $post_id, true );
				self::redirect_with_error( $referer, 'upload_save' );
			}

			update_post_meta( $post_id, '_video_private_file', $stored['file'] );
			update_post_meta( $post_id, '_video_original_name', $stored['name'] );
			update_post_meta( $post_id, '_video_mime', $stored['mime'] );
			update_post_meta( $post_id, '_video_size', $stored['size'] );
		}

		self::send_submission_notifications( $post_id );

		wp_safe_redirect(
			add_query_arg(
				'p1_submission_status',
				'success',
				remove_query_arg( [ 'p1_submission_error', 'p1_submission_status' ], $referer )
			)
		);
		exit;
	}

	/**
	 * Send the customer confirmation and admin notification for a saved submission.
	 *
	 * Transactional only: WooCommerce owns order/account email, FluentCRM owns any
	 * later lifecycle/marketing sequences, and this plugin owns submission-receipt mail.
	 * Public so it can be triggered and tested in isolation.
	 */
	public static function send_submission_notifications( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return;
		}

		$get = static function ( string $key ) use ( $post_id ): string {
			return (string) get_post_meta( $post_id, '_' . $key, true );
		};

		$parent_name = $get( 'parent_name' );
		$customer    = sanitize_email( $get( 'email' ) );
		$driver_name = $get( 'driver_name' );
		$video_url   = $get( 'video_url' );
		$has_file    = '' !== (string) get_post_meta( $post_id, '_video_private_file', true );
		$file_name   = (string) get_post_meta( $post_id, '_video_original_name', true );

		$from_name  = get_option( 'woocommerce_email_from_name' ) ?: get_bloginfo( 'name' );
		$from_email = get_option( 'woocommerce_email_from_address' ) ?: get_option( 'admin_email' );
		$headers    = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $from_name, $from_email ),
		];

		// 1. Customer confirmation.
		if ( is_email( $customer ) ) {
			$subject = 'We have your coaching footage details — P1 Race Academy';
			$body  = '<p>Hi ' . esc_html( $parent_name ?: 'there' ) . ',</p>';
			$body .= '<p>Thanks — P1 Race Academy has received your Online Driver Coaching submission';
			$body .= $driver_name ? ' for <strong>' . esc_html( $driver_name ) . '</strong>' : '';
			$body .= '.</p>';
			$body .= '<p>A coach will review the footage and the focus notes you provided, then send your written/video feedback. No action is needed from you right now.</p>';
			if ( $video_url ) {
				$body .= '<p>Footage link on file: <a href="' . esc_url( $video_url ) . '">' . esc_html( $video_url ) . '</a></p>';
			} elseif ( $has_file ) {
				$body .= '<p>You uploaded a video file with your submission. It is stored privately and only our coaching staff can access it.</p>';
			}
			$body .= '<p>— P1 Race Academy</p>';

			wp_mail( $customer, $subject, $body, $headers );
		}

		// 2. Admin / coach notification.
		$admin_email = get_option( 'admin_email' );
		if ( is_email( $admin_email ) ) {
			$edit_link = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
			$subject   = sprintf( 'New footage submission: %s', $driver_name ?: ( $parent_name ?: '#' . $post_id ) );

			$rows = [
				'Parent / contact' => $parent_name,
				'Email'            => $customer,
				'Phone'            => $get( 'phone' ),
				'Driver'           => $driver_name,
				'Driver age'       => $get( 'driver_age' ),
				'Kart class'       => $get( 'kart_class' ),
				'Track / event'    => $get( 'track_name' ),
				'Order number'     => $get( 'order_number' ),
				'Video link'       => $video_url,
				'Uploaded file'    => $has_file ? ( ( $file_name ?: 'video file' ) . ' — stored privately, open the submission to download' ) : '',
			];

			$body = '<p>A new Online Driver Coaching submission was received.</p><ul>';
			foreach ( $rows as $label => $value ) {
				if ( '' === $value ) {
					continue;
				}
				$is_url = ( 0 === strpos( $value, 'http' ) );
				$body  .= '<li><strong>' . esc_html( $label ) . ':</strong> '
					. ( $is_url ? '<a href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a>' : esc_html( $value ) )
					. '</li>';
			}
			$body .= '</ul>';
			$body .= '<p><strong>Review focus:</strong><br>' . nl2br( esc_html( $get( 'goals' ) ) ) . '</p>';
			$body .= '<p><a href="' . esc_url( $edit_link ) . '">Open this submission in WordPress admin</a></p>';

			wp_mail( $admin_email, $subject, $body, $headers );
		}
	}

	public static function render_order_received_next_step( int $order_id ): void {
		$order = self::get_online_coaching_order( $order_id );
		if ( ! $order || ! self::order_contains_online_coaching_product( $order ) ) {
			return;
		}

		echo self::get_order_next_step_markup() . self::get_account_setup_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public static function append_order_received_next_step( string $content ): string {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$order_id = absint( get_query_var( 'order-received' ) );
		$order    = self::get_online_coaching_order( $order_id );
		if ( ! $order ) {
			return $content;
		}

		return $content . self::get_order_next_step_markup() . self::get_account_setup_markup();
	}

	private static function sanitize_submission( array $data ): array {
		return [
			'parent_name'  => sanitize_text_field( $data['parent_name'] ?? '' ),
			'email'        => sanitize_email( $data['email'] ?? '' ),
			'phone'        => sanitize_text_field( $data['phone'] ?? '' ),
			'driver_name'  => sanitize_text_field( $data['driver_name'] ?? '' ),
			'driver_age'   => sanitize_text_field( $data['driver_age'] ?? '' ),
			'kart_class'   => sanitize_text_field( $data['kart_class'] ?? '' ),
			'track_name'   => sanitize_text_field( $data['track_name'] ?? '' ),
			'video_url'    => esc_url_raw( $data['video_url'] ?? '' ),
			'goals'        => sanitize_textarea_field( $data['goals'] ?? '' ),
			'order_number' => sanitize_text_field( $data['order_number'] ?? '' ),
			'consent'      => ! empty( $data['consent'] ) ? 'yes' : '',
		];
	}

	private static function has_video_file_upload(): bool {
		return isset( $_FILES[ self::VIDEO_FILE_FIELD ] )
			&& is_array( $_FILES[ self::VIDEO_FILE_FIELD ] )
			&& UPLOAD_ERR_NO_FILE !== (int) ( $_FILES[ self::VIDEO_FILE_FIELD ]['error'] ?? UPLOAD_ERR_NO_FILE );
	}

	private static function validate_video_upload(): string {
		$file = $_FILES[ self::VIDEO_FILE_FIELD ] ?? null;
		if ( ! is_array( $file ) ) {
			return 'upload';
		}

		$error = (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE );
		if ( UPLOAD_ERR_OK !== $error ) {
			if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
				return 'upload_size';
			}

			return 'upload';
		}

		$size = (int) ( $file['size'] ?? 0 );
		if ( $size <= 0 || $size > wp_max_upload_size() ) {
			return 'upload_size';
		}

		$allowed_mimes = self::get_allowed_video_mimes();
		$file_type     = wp_check_filetype_and_ext(
			$file['tmp_name'] ?? '',
			sanitize_file_name( $file['name'] ?? '' ),
			$allowed_mimes
		);

		if ( empty( $file_type['ext'] ) || empty( $file_type['type'] ) ) {
			return 'upload_type';
		}

		return '';
	}

	/**
	 * Private storage directory for footage, kept OUTSIDE the public web root
	 * (sibling of wp's docroot) so it can never be served by the web server.
	 * Filters let production hosts place this in a known private writable path.
	 */
	private static function private_dir(): string {
		$preferred = trailingslashit( dirname( untrailingslashit( ABSPATH ) ) ) . self::PRIVATE_DIRNAME;

		return (string) apply_filters( 'p1_video_submission_private_dir', $preferred );
	}

	/**
	 * Ensure the private dir exists with deny rules as belt-and-suspenders. If the
	 * outside-web-root path is not writable, uploads fail closed instead of falling
	 * back into default WordPress uploads.
	 */
	private static function ensure_private_dir(): string {
		$dir = self::private_dir();
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents( $htaccess, "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		$index = trailingslashit( $dir ) . 'index.html';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		return $dir;
	}

	/**
	 * Move the uploaded footage into private storage under an unguessable name.
	 * Returns ['file','name','mime','size'] on success or WP_Error on failure.
	 *
	 * @return array|\WP_Error
	 */
	private static function store_video_upload_private( int $post_id ) {
		$file = $_FILES[ self::VIDEO_FILE_FIELD ] ?? null;
		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) ) {
			return new WP_Error( 'p1_no_tmp', 'No uploaded file to store.' );
		}

		$original = sanitize_file_name( (string) ( $file['name'] ?? '' ) );
		$ext      = strtolower( pathinfo( $original, PATHINFO_EXTENSION ) );
		if ( ! array_key_exists( $ext, self::get_allowed_video_mimes() ) ) {
			return new WP_Error( 'p1_bad_ext', 'Unsupported video extension.' );
		}

		$dir = self::ensure_private_dir();
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return new WP_Error( 'p1_dir', 'Private storage directory is not writable.' );
		}

		$stored_name = $post_id . '-' . wp_generate_password( 24, false, false ) . '.' . $ext;
		$dest        = trailingslashit( $dir ) . $stored_name;

		$moved = @move_uploaded_file( $file['tmp_name'], $dest ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $moved ) {
			// Fallback for non-HTTP-POST contexts (e.g. isolated tests).
			$moved = @rename( $file['tmp_name'], $dest ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( ! $moved || ! is_file( $dest ) ) {
			return new WP_Error( 'p1_move', 'Could not store the uploaded file privately.' );
		}

		@chmod( $dest, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return [
			'file' => $stored_name,
			'name' => $original,
			'mime' => (string) ( $file['type'] ?? '' ),
			'size' => (int) ( $file['size'] ?? filesize( $dest ) ),
		];
	}

	/**
	 * Authenticated, nonce-checked download link for a submission's private footage.
	 * Only generated for, and only usable by, logged-in coaching staff.
	 */
	private static function get_download_url( int $post_id ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::DOWNLOAD_ACTION . '&submission=' . $post_id ),
			self::DOWNLOAD_ACTION . '_' . $post_id
		);
	}

	/**
	 * Stream a submission's private footage to an authorised coach. Access requires
	 * a logged-in user with edit_posts plus a valid per-submission nonce. The stored
	 * path is realpath-checked so it can never escape the private directory.
	 */
	public static function handle_download(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'You are not allowed to access this footage.', 'Forbidden', [ 'response' => 403 ] );
		}

		$post_id = isset( $_GET['submission'] ) ? absint( $_GET['submission'] ) : 0;
		check_admin_referer( self::DOWNLOAD_ACTION . '_' . $post_id );

		$post = $post_id ? get_post( $post_id ) : null;
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			wp_die( 'Submission not found.', 'Not found', [ 'response' => 404 ] );
		}

		$stored = (string) get_post_meta( $post_id, '_video_private_file', true );
		if ( '' === $stored ) {
			wp_die( 'No uploaded file on this submission.', 'Not found', [ 'response' => 404 ] );
		}

		$dir      = self::private_dir();
		$path     = trailingslashit( $dir ) . $stored;
		$real     = realpath( $path );
		$real_dir = realpath( $dir );
		if ( ! $real || ! $real_dir || 0 !== strpos( $real, trailingslashit( $real_dir ) ) || ! is_file( $real ) ) {
			wp_die( 'The footage file could not be found.', 'Not found', [ 'response' => 404 ] );
		}

		$name = (string) get_post_meta( $post_id, '_video_original_name', true );
		$name = '' !== $name ? $name : basename( $real );
		$mime = (string) get_post_meta( $post_id, '_video_mime', true );
		$mime = '' !== $mime ? $mime : 'application/octet-stream';

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $name ) . '"' );
		header( 'Content-Length: ' . filesize( $real ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		readfile( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	public static function register_meta_box(): void {
		add_meta_box(
			'p1-footage-access',
			'Coaching footage (private)',
			[ __CLASS__, 'render_meta_box' ],
			self::POST_TYPE,
			'side',
			'high'
		);
	}

	public static function render_meta_box( \WP_Post $post ): void {
		$stored    = (string) get_post_meta( $post->ID, '_video_private_file', true );
		$video_url = (string) get_post_meta( $post->ID, '_video_url', true );

		if ( '' !== $stored ) {
			$name = (string) get_post_meta( $post->ID, '_video_original_name', true );
			$size = (int) get_post_meta( $post->ID, '_video_size', true );
			printf(
				'<p><strong>Uploaded file:</strong><br>%s%s</p><p><a class="button button-primary" href="%s">Download footage</a></p><p style="color:#646970;">Stored privately outside the web root. This link is staff-only and nonce-protected.</p>',
				esc_html( $name ?: $stored ),
				$size ? ' <span style="color:#646970;">(' . esc_html( size_format( $size ) ) . ')</span>' : '',
				esc_url( self::get_download_url( $post->ID ) )
			);
		} elseif ( '' !== $video_url ) {
			printf(
				'<p><strong>Footage link:</strong><br><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
				esc_url( $video_url ),
				esc_html( $video_url )
			);
		} else {
			echo '<p style="color:#646970;">No footage file or link on this submission.</p>';
		}
	}

	private static function get_allowed_video_mimes(): array {
		return [
			'mp4'  => 'video/mp4',
			'mov'  => 'video/quicktime',
			'avi'  => 'video/x-msvideo',
			'webm' => 'video/webm',
		];
	}

	private static function order_contains_online_coaching_product( WC_Order $order ): bool {
		foreach ( $order->get_items() as $item ) {
			if (
				in_array( (int) $item->get_product_id(), self::PRODUCT_IDS, true )
				|| in_array( (int) $item->get_variation_id(), self::PRODUCT_IDS, true )
			) {
				return true;
			}
		}

		return false;
	}

	private static function get_online_coaching_order( int $order_id ): ?WC_Order {
		if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! self::order_contains_online_coaching_product( $order ) ) {
			return null;
		}

		$order_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
		if ( $order_key && ! hash_equals( $order->get_order_key(), $order_key ) ) {
			return null;
		}

		return $order;
	}

	private static function get_order_next_step_markup(): string {
		$submission_url = add_query_arg( 'p1_submission', '1', home_url( '/contact/' ) );

		return sprintf(
			'<section class="p1-video-submission-flow__order-next-step" style="border:1px solid #d1d5db;padding:24px;margin:28px 0;background:#f9fafb;"><h2 style="margin-top:0;">Next step: submit your footage</h2><p>Upload the video file or send a video link with driver context so P1 Race Academy can complete the coaching review. Onboard / forward-facing footage of 3&ndash;5 clean laps works best.</p><p style="color:#475569;">Your review is delivered within <strong>3 business days</strong> of us receiving usable footage.</p><p><a class="button wp-element-button" href="%s">Submit footage details</a></p></section>',
			esc_url( $submission_url )
		);
	}

	/**
	 * Post-checkout account-claim prompt: lets a just-registered customer set a password
	 * they actually chose, so they can log back in later (the checkout auto-password is
	 * never shown to them, and locally the new-account email is log-only).
	 */
	private static function get_account_setup_markup(): string {
		if (
			! is_user_logged_in()
			|| ! function_exists( 'wc_get_account_endpoint_url' )
			|| 'yes' !== get_option( 'woocommerce_registration_generate_password' )
		) {
			return '';
		}

		$user = wp_get_current_user();
		$key  = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			return '';
		}

		$set_password_url = add_query_arg(
			[
				'action' => 'rp',
				'key'    => $key,
				'login'  => rawurlencode( $user->user_login ),
			],
			wc_get_account_endpoint_url( 'lost-password' )
		);

		return sprintf(
			'<section class="p1-account-setup" style="border:1px solid #d1d5db;padding:24px;margin:28px 0;background:#f9fafb;"><h2 style="margin-top:0;">Set your password</h2><p>Your student account is ready. Choose a password so you can log back in anytime to access your course and feedback.</p><p><a class="button wp-element-button" href="%s">Set your password</a> <a href="%s" style="margin-left:8px;">Go to My Account</a></p></section>',
			esc_url( $set_password_url ),
			esc_url( wc_get_page_permalink( 'myaccount' ) )
		);
	}

	private static function get_missing_required_fields( array $submission ): array {
		$required = [ 'parent_name', 'email', 'driver_name', 'goals', 'consent' ];

		return array_filter(
			$required,
			static function ( string $field ) use ( $submission ): bool {
				return empty( $submission[ $field ] );
			}
		);
	}

	private static function redirect_with_error( string $referer, string $error ): void {
		wp_safe_redirect(
			add_query_arg(
				'p1_submission_error',
				$error,
				remove_query_arg( [ 'p1_submission_error', 'p1_submission_status' ], $referer )
			)
		);
		exit;
	}

	private static function get_error_message( string $error ): string {
		$messages = [
			'nonce'          => 'Please refresh the page and try again.',
			'missing'        => 'Please fill in every required field.',
			'email'          => 'Please enter a valid email address.',
			'video_required' => 'Please upload a video file or provide a video link.',
			'video_url'      => 'Please enter a valid share link for the footage.',
			'upload'         => 'The video file could not be uploaded. Try again or use a video link.',
			'upload_size'    => 'The video file is too large for the local WordPress upload limit. Use a share link instead.',
			'upload_type'    => 'Please upload an MP4, MOV, AVI, or WebM video file.',
			'upload_save'    => 'WordPress could not save the video file. Try again or use a video link.',
			'save'           => 'WordPress could not save the submission. Try again in a moment.',
		];

		return $messages[ $error ] ?? 'Please check the form and try again.';
	}
}

P1_Video_Submission_Flow::init();
