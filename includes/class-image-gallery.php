<?php
/**
 * Image Gallery - Handles multi-image upload and gallery display.
 *
 * @package Community_Auctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Image_Gallery
 *
 * Handles image gallery upload, storage, and display.
 */
class Community_Auctions_Image_Gallery {

	/**
	 * Meta key for storing gallery image IDs.
	 */
	const META_KEY = 'ca_gallery_ids';

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_shortcode( 'community_auction_gallery', array( __CLASS__, 'render_shortcode' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'community-auctions/v1',
			'/gallery/upload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_upload' ),
				'permission_callback' => array( __CLASS__, 'can_upload' ),
			)
		);

		register_rest_route(
			'community-auctions/v1',
			'/gallery/reorder',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_reorder' ),
				'permission_callback' => array( __CLASS__, 'can_edit_gallery' ),
				'args'                => array(
					'auction_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'image_ids'  => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'integer' ),
					),
				),
			)
		);

		register_rest_route(
			'community-auctions/v1',
			'/gallery/delete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_delete' ),
				'permission_callback' => array( __CLASS__, 'can_edit_gallery' ),
				'args'                => array(
					'auction_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'image_id'   => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Register frontend assets.
	 */
	public static function register_assets() {
		$js_url  = plugin_dir_url( __DIR__ ) . 'assets/js/gallery.js';
		$css_url = plugin_dir_url( __DIR__ ) . 'assets/css/gallery.css';

		wp_register_script(
			'community-auctions-gallery',
			$js_url,
			array(),
			Community_Auctions_Plugin::VERSION,
			true
		);

		wp_register_style(
			'community-auctions-gallery',
			$css_url,
			array(),
			Community_Auctions_Plugin::VERSION
		);
	}

	/**
	 * Enqueue gallery assets.
	 */
	public static function enqueue_assets() {
		wp_enqueue_script( 'community-auctions-gallery' );
		wp_enqueue_style( 'community-auctions-gallery' );

		wp_localize_script(
			'community-auctions-gallery',
			'CommunityAuctionsGallery',
			array(
				'uploadUrl'   => rest_url( 'community-auctions/v1/gallery/upload' ),
				'reorderUrl'  => rest_url( 'community-auctions/v1/gallery/reorder' ),
				'deleteUrl'   => rest_url( 'community-auctions/v1/gallery/delete' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'maxFiles'    => 10,
				'maxFileSize' => wp_max_upload_size(),
				'i18n'        => array(
					'uploading'   => __( 'Uploading...', 'community-auctions' ),
					'uploadError' => __( 'Upload failed. Please try again.', 'community-auctions' ),
					'maxFiles'    => __( 'Maximum 10 images allowed.', 'community-auctions' ),
					'maxSize'     => __( 'File is too large.', 'community-auctions' ),
					'invalidType' => __( 'Only images are allowed.', 'community-auctions' ),
					'confirm'     => __( 'Remove this image?', 'community-auctions' ),
				),
			)
		);
	}

	/**
	 * Check if user can upload images.
	 *
	 * @return bool
	 */
	public static function can_upload() {
		return is_user_logged_in() && current_user_can( 'ca_create_auction' );
	}

	/**
	 * Check if user can edit gallery.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public static function can_edit_gallery( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$auction_id = absint( $request->get_param( 'auction_id' ) );
		$auction    = get_post( $auction_id );

		if ( ! $auction || 'auction' !== $auction->post_type ) {
			return false;
		}

		// Allow author or admin.
		return absint( $auction->post_author ) === get_current_user_id() || current_user_can( 'ca_manage_auctions' );
	}

	/**
	 * Handle image upload.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function handle_upload( WP_REST_Request $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No file uploaded.', 'community-auctions' ),
				),
				400
			);
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$file = $files['file'];

		// Validate file type.
		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		if ( ! in_array( $file['type'], $allowed_types, true ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.', 'community-auctions' ),
				),
				400
			);
		}

		// Handle upload.
		$upload = wp_handle_upload(
			$file,
			array( 'test_form' => false )
		);

		if ( isset( $upload['error'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $upload['error'],
				),
				400
			);
		}

		// Create attachment.
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $upload['type'],
				'post_title'     => sanitize_file_name( pathinfo( $upload['file'], PATHINFO_FILENAME ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		if ( is_wp_error( $attachment_id ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $attachment_id->get_error_message(),
				),
				400
			);
		}

		// Generate metadata.
		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'id'        => $attachment_id,
					'url'       => wp_get_attachment_url( $attachment_id ),
					'thumbnail' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
					'medium'    => wp_get_attachment_image_url( $attachment_id, 'medium' ),
					'large'     => wp_get_attachment_image_url( $attachment_id, 'large' ),
					'full'      => wp_get_attachment_image_url( $attachment_id, 'full' ),
				),
			),
			200
		);
	}

	/**
	 * Handle gallery reorder.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function handle_reorder( WP_REST_Request $request ) {
		$auction_id = absint( $request->get_param( 'auction_id' ) );
		$image_ids  = array_map( 'absint', $request->get_param( 'image_ids' ) );

		self::set_gallery_ids( $auction_id, $image_ids );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Gallery order updated.', 'community-auctions' ),
			),
			200
		);
	}

	/**
	 * Handle image deletion from gallery.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function handle_delete( WP_REST_Request $request ) {
		$auction_id = absint( $request->get_param( 'auction_id' ) );
		$image_id   = absint( $request->get_param( 'image_id' ) );

		$current_ids = self::get_gallery_ids( $auction_id );
		$new_ids     = array_diff( $current_ids, array( $image_id ) );

		self::set_gallery_ids( $auction_id, $new_ids );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Image removed from gallery.', 'community-auctions' ),
			),
			200
		);
	}

	/**
	 * Get gallery image IDs for an auction.
	 *
	 * @param int $auction_id Auction ID.
	 * @return array Array of attachment IDs.
	 */
	public static function get_gallery_ids( $auction_id ) {
		$ids = get_post_meta( $auction_id, self::META_KEY, true );

		if ( empty( $ids ) ) {
			return array();
		}

		return array_filter( array_map( 'absint', explode( ',', $ids ) ) );
	}

	/**
	 * Set gallery image IDs for an auction.
	 *
	 * @param int   $auction_id Auction ID.
	 * @param array $image_ids  Array of attachment IDs.
	 */
	public static function set_gallery_ids( $auction_id, $image_ids ) {
		$image_ids = array_filter( array_map( 'absint', (array) $image_ids ) );

		if ( empty( $image_ids ) ) {
			delete_post_meta( $auction_id, self::META_KEY );
		} else {
			update_post_meta( $auction_id, self::META_KEY, implode( ',', $image_ids ) );
		}
	}

	/**
	 * Add image to gallery.
	 *
	 * @param int $auction_id Auction ID.
	 * @param int $image_id   Attachment ID.
	 */
	public static function add_image( $auction_id, $image_id ) {
		$current_ids   = self::get_gallery_ids( $auction_id );
		$current_ids[] = absint( $image_id );

		self::set_gallery_ids( $auction_id, array_unique( $current_ids ) );
	}

	/**
	 * Render gallery shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'        => 0,
				'size'      => 'medium',
				'lightbox'  => '1',
				'columns'   => 3,
			),
			$atts
		);

		$auction_id = absint( $atts['id'] );
		if ( ! $auction_id ) {
			$auction_id = get_the_ID();
		}

		if ( ! $auction_id ) {
			return '';
		}

		$image_ids = self::get_gallery_ids( $auction_id );

		if ( empty( $image_ids ) ) {
			return '';
		}

		self::enqueue_assets();

		$size    = sanitize_text_field( $atts['size'] );
		$columns = max( 1, min( 6, absint( $atts['columns'] ) ) );

		ob_start();
		?>
		<div class="ca-gallery ca-gallery--cols-<?php echo esc_attr( $columns ); ?>" data-auction-id="<?php echo esc_attr( $auction_id ); ?>">
			<?php foreach ( $image_ids as $index => $image_id ) : ?>
				<?php
				$thumb_url = wp_get_attachment_image_url( $image_id, $size );
				$full_url  = wp_get_attachment_image_url( $image_id, 'full' );
				$alt       = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
				$title     = get_the_title( $image_id );

				if ( ! $thumb_url ) {
					continue;
				}
				?>
				<div class="ca-gallery-item <?php echo 0 === $index ? 'ca-gallery-item--primary' : ''; ?>">
					<?php if ( '1' === $atts['lightbox'] ) : ?>
						<a href="<?php echo esc_url( $full_url ); ?>" class="ca-gallery-link" data-lightbox="auction-<?php echo esc_attr( $auction_id ); ?>" title="<?php echo esc_attr( $title ); ?>">
					<?php endif; ?>
						<img
							src="<?php echo esc_url( $thumb_url ); ?>"
							alt="<?php echo esc_attr( $alt ?: $title ); ?>"
							class="ca-gallery-image"
							loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>"
						/>
					<?php if ( '1' === $atts['lightbox'] ) : ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( '1' === $atts['lightbox'] ) : ?>
			<div class="ca-lightbox" id="ca-lightbox-<?php echo esc_attr( $auction_id ); ?>" aria-hidden="true" role="dialog" aria-modal="true">
				<div class="ca-lightbox-overlay"></div>
				<div class="ca-lightbox-content">
					<button type="button" class="ca-lightbox-close" aria-label="<?php esc_attr_e( 'Close', 'community-auctions' ); ?>">&times;</button>
					<button type="button" class="ca-lightbox-prev" aria-label="<?php esc_attr_e( 'Previous', 'community-auctions' ); ?>">&lsaquo;</button>
					<button type="button" class="ca-lightbox-next" aria-label="<?php esc_attr_e( 'Next', 'community-auctions' ); ?>">&rsaquo;</button>
					<img src="" alt="" class="ca-lightbox-image" />
					<div class="ca-lightbox-caption"></div>
				</div>
			</div>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render gallery upload field for frontend form.
	 *
	 * @param int $auction_id Optional auction ID for existing gallery.
	 * @return string HTML output.
	 */
	public static function render_upload_field( $auction_id = 0 ) {
		self::enqueue_assets();

		$existing_ids = $auction_id ? self::get_gallery_ids( $auction_id ) : array();

		ob_start();
		?>
		<div class="ca-gallery-upload" data-auction-id="<?php echo esc_attr( $auction_id ); ?>">
			<label><?php esc_html_e( 'Images', 'community-auctions' ); ?></label>

			<div class="ca-gallery-upload-zone" role="button" tabindex="0" aria-label="<?php esc_attr_e( 'Click or drop images to upload', 'community-auctions' ); ?>">
				<input
					type="file"
					name="ca_gallery_files[]"
					id="ca-gallery-files"
					multiple
					accept="image/jpeg,image/png,image/gif,image/webp"
					class="ca-gallery-file-input"
					aria-describedby="ca-gallery-help"
				/>
				<p class="ca-gallery-upload-text">
					<?php esc_html_e( 'Click or drag images here to upload', 'community-auctions' ); ?>
				</p>
				<p id="ca-gallery-help" class="ca-gallery-upload-help">
					<?php esc_html_e( 'Max 10 images. JPEG, PNG, GIF, WebP allowed.', 'community-auctions' ); ?>
				</p>
			</div>

			<div class="ca-gallery-upload-status" role="status" aria-live="polite"></div>

			<ul class="ca-gallery-preview" role="list">
				<?php foreach ( $existing_ids as $image_id ) : ?>
					<?php
					$thumb_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
					if ( ! $thumb_url ) {
						continue;
					}
					?>
					<li class="ca-gallery-preview-item" data-id="<?php echo esc_attr( $image_id ); ?>">
						<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" />
						<input type="hidden" name="ca_gallery_ids[]" value="<?php echo esc_attr( $image_id ); ?>" />
						<button type="button" class="ca-gallery-remove" aria-label="<?php esc_attr_e( 'Remove image', 'community-auctions' ); ?>">&times;</button>
						<span class="ca-gallery-drag-handle" aria-label="<?php esc_attr_e( 'Drag to reorder', 'community-auctions' ); ?>">&equiv;</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render inline gallery for single auction.
	 *
	 * @param int $auction_id Auction ID.
	 * @return string HTML output.
	 */
	public static function render_inline( $auction_id ) {
		return self::render_shortcode( array( 'id' => $auction_id ) );
	}
}
