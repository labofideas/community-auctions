<?php
/**
 * Frontend Forms for Auction Submission
 *
 * @package CommunityAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend Forms Class.
 */
class Community_Auctions_Frontend_Forms {

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_shortcode( 'community_auction_submit', array( __CLASS__, 'render_submit_form' ) );
		add_action( 'init', array( __CLASS__, 'handle_submit' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_form_assets' ) );
	}

	/**
	 * Enqueue form assets.
	 */
	public static function enqueue_form_assets() {
		global $post;

		if ( ! $post || ! has_shortcode( $post->post_content, 'community_auction_submit' ) ) {
			return;
		}

		wp_enqueue_style(
			'ca-submit-form',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/submit-form.css',
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'ca-submit-form',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/submit-form.js',
			array(),
			'1.0.0',
			true
		);
	}

	/**
	 * Render the auction submit form.
	 *
	 * @return string Form HTML.
	 */
	public static function render_submit_form() {
		if ( ! is_user_logged_in() ) {
			return self::render_login_notice();
		}

		if ( ! current_user_can( 'ca_create_auction' ) ) {
			return self::render_permission_notice();
		}

		if ( ! self::is_payment_provider_selected() ) {
			return self::render_payment_notice();
		}

		$settings = Community_Auctions_Settings::get_settings();
		$default_visibility = $settings['group_visibility_default'] ?? 'public';
		$default_proxy = ! empty( $settings['proxy_default'] );
		$currency_symbol = isset( $settings['currency_symbol'] ) ? $settings['currency_symbol'] : '$';
		$group_id = 0;
		$force_group_only = false;

		if ( function_exists( 'bp_is_group' ) && bp_is_group() && function_exists( 'groups_get_current_group_id' ) ) {
			$group_id = groups_get_current_group_id();
			if ( $group_id && function_exists( 'groups_get_groupmeta' ) ) {
				$force_group_only = (bool) groups_get_groupmeta( $group_id, 'ca_group_only', true );
			}
		}

		// Default dates.
		$default_start = gmdate( 'Y-m-d\TH:i', strtotime( '+1 hour' ) );
		$default_end = gmdate( 'Y-m-d\TH:i', strtotime( '+7 days' ) );

		ob_start();
		?>
		<div class="ca-submit-wrapper">
			<div class="ca-submit-header">
				<h2><?php esc_html_e( 'Create New Auction', 'community-auctions' ); ?></h2>
				<p class="ca-submit-subtitle"><?php esc_html_e( 'Fill in the details below to list your item for auction.', 'community-auctions' ); ?></p>
			</div>

			<!-- Progress Steps -->
			<div class="ca-form-steps" role="navigation" aria-label="<?php esc_attr_e( 'Form progress', 'community-auctions' ); ?>">
				<div class="ca-step ca-step-active" data-step="1">
					<span class="ca-step-number">1</span>
					<span class="ca-step-label"><?php esc_html_e( 'Item Details', 'community-auctions' ); ?></span>
				</div>
				<div class="ca-step" data-step="2">
					<span class="ca-step-number">2</span>
					<span class="ca-step-label"><?php esc_html_e( 'Pricing', 'community-auctions' ); ?></span>
				</div>
				<div class="ca-step" data-step="3">
					<span class="ca-step-number">3</span>
					<span class="ca-step-label"><?php esc_html_e( 'Schedule', 'community-auctions' ); ?></span>
				</div>
				<div class="ca-step" data-step="4">
					<span class="ca-step-number">4</span>
					<span class="ca-step-label"><?php esc_html_e( 'Review', 'community-auctions' ); ?></span>
				</div>
			</div>

			<form method="post" class="ca-submit-form" id="ca-submit-form">
				<?php wp_nonce_field( 'community_auctions_submit', 'community_auctions_nonce' ); ?>
				<?php if ( $group_id ) : ?>
					<input type="hidden" name="ca_group_id" value="<?php echo esc_attr( $group_id ); ?>" />
				<?php endif; ?>

				<div class="ca-form-status" role="status" aria-live="polite"></div>

				<!-- Step 1: Item Details -->
				<div class="ca-form-section ca-section-active" data-section="1">
					<div class="ca-section-header">
						<h3><?php esc_html_e( 'Item Details', 'community-auctions' ); ?></h3>
						<p><?php esc_html_e( 'Describe what you\'re selling.', 'community-auctions' ); ?></p>
					</div>

					<div class="ca-form-card">
						<div class="ca-form-group">
							<label for="ca_title" class="ca-label ca-required">
								<?php esc_html_e( 'Title', 'community-auctions' ); ?>
							</label>
							<input
								type="text"
								id="ca_title"
								name="ca_title"
								class="ca-input"
								placeholder="<?php esc_attr_e( 'e.g., Vintage Rolex Submariner Watch', 'community-auctions' ); ?>"
								required
								maxlength="200"
							/>
							<span class="ca-hint"><?php esc_html_e( 'Be specific and descriptive', 'community-auctions' ); ?></span>
						</div>

						<div class="ca-form-group">
							<label for="ca_description" class="ca-label">
								<?php esc_html_e( 'Description', 'community-auctions' ); ?>
							</label>
							<textarea
								id="ca_description"
								name="ca_description"
								class="ca-textarea"
								rows="6"
								placeholder="<?php esc_attr_e( 'Describe your item in detail - condition, features, history, etc.', 'community-auctions' ); ?>"
							></textarea>
							<span class="ca-hint"><?php esc_html_e( 'Include condition, dimensions, history, and any defects', 'community-auctions' ); ?></span>
						</div>

						<div class="ca-form-group">
							<label class="ca-label"><?php esc_html_e( 'Photos', 'community-auctions' ); ?></label>
							<div class="ca-gallery-upload-wrapper">
								<?php echo Community_Auctions_Image_Gallery::render_upload_field(); ?>
							</div>
							<span class="ca-hint"><?php esc_html_e( 'Add up to 10 photos. First photo will be the main image.', 'community-auctions' ); ?></span>
						</div>

						<div class="ca-form-group">
							<label for="ca_category" class="ca-label">
								<?php esc_html_e( 'Category', 'community-auctions' ); ?>
							</label>
							<?php
							echo Community_Auctions_Taxonomy::render_category_selector(
								0,
								array(
									'multiple'   => false,
									'class'      => 'ca-select',
									'id'         => 'ca_category',
									'show_label' => false,
								)
							);
							?>
						</div>
					</div>

					<div class="ca-form-actions">
						<button type="button" class="ca-btn ca-btn-primary ca-btn-next" data-next="2">
							<?php esc_html_e( 'Continue to Pricing', 'community-auctions' ); ?>
							<span class="ca-btn-icon">&rarr;</span>
						</button>
					</div>
				</div>

				<!-- Step 2: Pricing -->
				<div class="ca-form-section" data-section="2">
					<div class="ca-section-header">
						<h3><?php esc_html_e( 'Pricing', 'community-auctions' ); ?></h3>
						<p><?php esc_html_e( 'Set your starting price and bidding rules.', 'community-auctions' ); ?></p>
					</div>

					<div class="ca-form-card">
						<div class="ca-form-row">
							<div class="ca-form-group ca-form-group-half">
								<label for="ca_start_price" class="ca-label ca-required">
									<?php esc_html_e( 'Starting Price', 'community-auctions' ); ?>
								</label>
								<div class="ca-input-with-prefix">
									<span class="ca-input-prefix"><?php echo esc_html( $currency_symbol ); ?></span>
									<input
										type="number"
										id="ca_start_price"
										name="ca_start_price"
										class="ca-input"
										step="0.01"
										min="0.01"
										placeholder="0.00"
										required
									/>
								</div>
								<span class="ca-hint"><?php esc_html_e( 'The opening bid amount', 'community-auctions' ); ?></span>
							</div>

							<div class="ca-form-group ca-form-group-half">
								<label for="ca_min_increment" class="ca-label ca-required">
									<?php esc_html_e( 'Bid Increment', 'community-auctions' ); ?>
								</label>
								<div class="ca-input-with-prefix">
									<span class="ca-input-prefix"><?php echo esc_html( $currency_symbol ); ?></span>
									<input
										type="number"
										id="ca_min_increment"
										name="ca_min_increment"
										class="ca-input"
										step="0.01"
										min="0.01"
										value="1.00"
										required
									/>
								</div>
								<span class="ca-hint"><?php esc_html_e( 'Minimum amount each bid must increase', 'community-auctions' ); ?></span>
							</div>
						</div>

						<div class="ca-form-group">
							<label for="ca_reserve_price" class="ca-label">
								<?php esc_html_e( 'Reserve Price', 'community-auctions' ); ?>
								<span class="ca-label-optional"><?php esc_html_e( '(Optional)', 'community-auctions' ); ?></span>
							</label>
							<div class="ca-input-with-prefix">
								<span class="ca-input-prefix"><?php echo esc_html( $currency_symbol ); ?></span>
								<input
									type="number"
									id="ca_reserve_price"
									name="ca_reserve_price"
									class="ca-input"
									step="0.01"
									min="0"
									placeholder="0.00"
								/>
							</div>
							<span class="ca-hint"><?php esc_html_e( 'Minimum price you\'ll accept. Hidden from bidders.', 'community-auctions' ); ?></span>
						</div>

						<?php if ( Community_Auctions_Buy_Now::is_enabled_globally() ) : ?>
							<div class="ca-form-group ca-toggle-section">
								<label class="ca-toggle">
									<input type="checkbox" name="ca_buy_now_enabled" value="1" id="ca_buy_now_toggle" />
									<span class="ca-toggle-slider"></span>
									<span class="ca-toggle-label"><?php esc_html_e( 'Enable Buy It Now', 'community-auctions' ); ?></span>
								</label>
								<span class="ca-hint"><?php esc_html_e( 'Allow instant purchase at a fixed price', 'community-auctions' ); ?></span>
							</div>

							<div class="ca-form-group ca-conditional-field" id="ca_buy_now_field" style="display: none;">
								<label for="ca_buy_now_price" class="ca-label">
									<?php esc_html_e( 'Buy It Now Price', 'community-auctions' ); ?>
								</label>
								<div class="ca-input-with-prefix">
									<span class="ca-input-prefix"><?php echo esc_html( $currency_symbol ); ?></span>
									<input
										type="number"
										id="ca_buy_now_price"
										name="ca_buy_now_price"
										class="ca-input"
										step="0.01"
										min="0"
										placeholder="0.00"
									/>
								</div>
								<span class="ca-hint"><?php esc_html_e( 'Price for instant purchase (should be higher than starting price)', 'community-auctions' ); ?></span>
							</div>
						<?php endif; ?>
					</div>

					<div class="ca-form-actions">
						<button type="button" class="ca-btn ca-btn-secondary ca-btn-prev" data-prev="1">
							<span class="ca-btn-icon">&larr;</span>
							<?php esc_html_e( 'Back', 'community-auctions' ); ?>
						</button>
						<button type="button" class="ca-btn ca-btn-primary ca-btn-next" data-next="3">
							<?php esc_html_e( 'Continue to Schedule', 'community-auctions' ); ?>
							<span class="ca-btn-icon">&rarr;</span>
						</button>
					</div>
				</div>

				<!-- Step 3: Schedule -->
				<div class="ca-form-section" data-section="3">
					<div class="ca-section-header">
						<h3><?php esc_html_e( 'Schedule', 'community-auctions' ); ?></h3>
						<p><?php esc_html_e( 'Set when your auction starts and ends.', 'community-auctions' ); ?></p>
					</div>

					<div class="ca-form-card">
						<div class="ca-form-row">
							<div class="ca-form-group ca-form-group-half">
								<label for="ca_start_at" class="ca-label ca-required">
									<?php esc_html_e( 'Start Date & Time', 'community-auctions' ); ?>
								</label>
								<input
									type="datetime-local"
									id="ca_start_at"
									name="ca_start_at"
									class="ca-input"
									value="<?php echo esc_attr( $default_start ); ?>"
									required
								/>
								<span class="ca-hint"><?php esc_html_e( 'When bidding opens', 'community-auctions' ); ?></span>
							</div>

							<div class="ca-form-group ca-form-group-half">
								<label for="ca_end_at" class="ca-label ca-required">
									<?php esc_html_e( 'End Date & Time', 'community-auctions' ); ?>
								</label>
								<input
									type="datetime-local"
									id="ca_end_at"
									name="ca_end_at"
									class="ca-input"
									value="<?php echo esc_attr( $default_end ); ?>"
									required
								/>
								<span class="ca-hint"><?php esc_html_e( 'When bidding closes', 'community-auctions' ); ?></span>
							</div>
						</div>

						<div class="ca-duration-preview" id="ca-duration-preview">
							<span class="ca-duration-icon">⏱</span>
							<span class="ca-duration-text"><?php esc_html_e( 'Duration: 7 days', 'community-auctions' ); ?></span>
						</div>

						<div class="ca-form-group ca-toggle-section">
							<label class="ca-toggle">
								<input type="checkbox" name="ca_proxy_enabled" value="1" <?php checked( $default_proxy ); ?> />
								<span class="ca-toggle-slider"></span>
								<span class="ca-toggle-label"><?php esc_html_e( 'Enable Proxy Bidding', 'community-auctions' ); ?></span>
							</label>
							<span class="ca-hint"><?php esc_html_e( 'Allow bidders to set a maximum bid and auto-bid up to that amount', 'community-auctions' ); ?></span>
						</div>

						<?php if ( ! $force_group_only ) : ?>
							<div class="ca-form-group">
								<label for="ca_visibility" class="ca-label">
									<?php esc_html_e( 'Visibility', 'community-auctions' ); ?>
								</label>
								<select id="ca_visibility" name="ca_visibility" class="ca-select">
									<option value="public" <?php selected( $default_visibility, 'public' ); ?>>
										<?php esc_html_e( 'Public - Anyone can view and bid', 'community-auctions' ); ?>
									</option>
									<option value="group_only" <?php selected( $default_visibility, 'group_only' ); ?>>
										<?php esc_html_e( 'Group Only - Only group members can view', 'community-auctions' ); ?>
									</option>
								</select>
							</div>
						<?php else : ?>
							<input type="hidden" name="ca_visibility" value="group_only" />
							<div class="ca-notice ca-notice-info">
								<?php esc_html_e( 'This auction will only be visible to group members.', 'community-auctions' ); ?>
							</div>
						<?php endif; ?>
					</div>

					<div class="ca-form-actions">
						<button type="button" class="ca-btn ca-btn-secondary ca-btn-prev" data-prev="2">
							<span class="ca-btn-icon">&larr;</span>
							<?php esc_html_e( 'Back', 'community-auctions' ); ?>
						</button>
						<button type="button" class="ca-btn ca-btn-primary ca-btn-next" data-next="4">
							<?php esc_html_e( 'Review Auction', 'community-auctions' ); ?>
							<span class="ca-btn-icon">&rarr;</span>
						</button>
					</div>
				</div>

				<!-- Step 4: Review -->
				<div class="ca-form-section" data-section="4">
					<div class="ca-section-header">
						<h3><?php esc_html_e( 'Review Your Auction', 'community-auctions' ); ?></h3>
						<p><?php esc_html_e( 'Double-check everything before submitting.', 'community-auctions' ); ?></p>
					</div>

					<div class="ca-form-card ca-review-card">
						<div class="ca-review-section">
							<h4><?php esc_html_e( 'Item Details', 'community-auctions' ); ?></h4>
							<dl class="ca-review-list">
								<dt><?php esc_html_e( 'Title', 'community-auctions' ); ?></dt>
								<dd id="review-title">-</dd>
								<dt><?php esc_html_e( 'Description', 'community-auctions' ); ?></dt>
								<dd id="review-description">-</dd>
							</dl>
						</div>

						<div class="ca-review-section">
							<h4><?php esc_html_e( 'Pricing', 'community-auctions' ); ?></h4>
							<dl class="ca-review-list">
								<dt><?php esc_html_e( 'Starting Price', 'community-auctions' ); ?></dt>
								<dd id="review-start-price">-</dd>
								<dt><?php esc_html_e( 'Bid Increment', 'community-auctions' ); ?></dt>
								<dd id="review-increment">-</dd>
								<dt><?php esc_html_e( 'Reserve Price', 'community-auctions' ); ?></dt>
								<dd id="review-reserve">-</dd>
							</dl>
						</div>

						<div class="ca-review-section">
							<h4><?php esc_html_e( 'Schedule', 'community-auctions' ); ?></h4>
							<dl class="ca-review-list">
								<dt><?php esc_html_e( 'Starts', 'community-auctions' ); ?></dt>
								<dd id="review-start">-</dd>
								<dt><?php esc_html_e( 'Ends', 'community-auctions' ); ?></dt>
								<dd id="review-end">-</dd>
								<dt><?php esc_html_e( 'Duration', 'community-auctions' ); ?></dt>
								<dd id="review-duration">-</dd>
							</dl>
						</div>
					</div>

					<div class="ca-form-actions">
						<button type="button" class="ca-btn ca-btn-secondary ca-btn-prev" data-prev="3">
							<span class="ca-btn-icon">&larr;</span>
							<?php esc_html_e( 'Back', 'community-auctions' ); ?>
						</button>
						<button type="submit" name="ca_submit" value="1" class="ca-btn ca-btn-success ca-btn-submit">
							<?php esc_html_e( 'Submit Auction', 'community-auctions' ); ?>
							<span class="ca-btn-icon">✓</span>
						</button>
					</div>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render login notice.
	 *
	 * @return string HTML.
	 */
	private static function render_login_notice() {
		ob_start();
		?>
		<div class="ca-notice-box ca-notice-login">
			<div class="ca-notice-icon">🔐</div>
			<h3><?php esc_html_e( 'Login Required', 'community-auctions' ); ?></h3>
			<p><?php esc_html_e( 'Please log in to create an auction.', 'community-auctions' ); ?></p>
			<div class="ca-notice-actions">
				<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="ca-btn ca-btn-primary">
					<?php esc_html_e( 'Log In', 'community-auctions' ); ?>
				</a>
				<?php if ( get_option( 'users_can_register' ) ) : ?>
					<a href="<?php echo esc_url( wp_registration_url() ); ?>" class="ca-btn ca-btn-secondary">
						<?php esc_html_e( 'Register', 'community-auctions' ); ?>
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render permission notice.
	 *
	 * @return string HTML.
	 */
	private static function render_permission_notice() {
		ob_start();
		?>
		<div class="ca-notice-box ca-notice-error">
			<div class="ca-notice-icon">⚠️</div>
			<h3><?php esc_html_e( 'Permission Denied', 'community-auctions' ); ?></h3>
			<p><?php esc_html_e( 'You do not have permission to create auctions. Please contact an administrator.', 'community-auctions' ); ?></p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render payment provider notice.
	 *
	 * @return string HTML.
	 */
	private static function render_payment_notice() {
		ob_start();
		?>
		<div class="ca-notice-box ca-notice-warning">
			<div class="ca-notice-icon">💳</div>
			<h3><?php esc_html_e( 'Setup Required', 'community-auctions' ); ?></h3>
			<p><?php esc_html_e( 'A payment provider must be configured before auctions can be created.', 'community-auctions' ); ?></p>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=community-auctions' ) ); ?>" class="ca-btn ca-btn-primary">
					<?php esc_html_e( 'Configure Settings', 'community-auctions' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Handle form submission.
	 */
	public static function handle_submit() {
		if ( ! isset( $_POST['ca_submit'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! current_user_can( 'ca_create_auction' ) ) {
			return;
		}

		if ( empty( $_POST['community_auctions_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['community_auctions_nonce'] ) ), 'community_auctions_submit' ) ) {
			return;
		}

		if ( ! self::is_payment_provider_selected() ) {
			return;
		}

		$title         = sanitize_text_field( wp_unslash( $_POST['ca_title'] ?? '' ) );
		$description   = wp_kses_post( wp_unslash( $_POST['ca_description'] ?? '' ) );
		$start_at      = sanitize_text_field( wp_unslash( $_POST['ca_start_at'] ?? '' ) );
		$end_at        = sanitize_text_field( wp_unslash( $_POST['ca_end_at'] ?? '' ) );
		$start_price   = floatval( wp_unslash( $_POST['ca_start_price'] ?? 0 ) );
		$reserve_price = floatval( wp_unslash( $_POST['ca_reserve_price'] ?? 0 ) );
		$min_increment = floatval( wp_unslash( $_POST['ca_min_increment'] ?? 0 ) );
		$visibility    = sanitize_text_field( wp_unslash( $_POST['ca_visibility'] ?? 'public' ) );
		$proxy_enabled = ! empty( $_POST['ca_proxy_enabled'] ) ? 1 : 0;
		$group_id      = absint( wp_unslash( $_POST['ca_group_id'] ?? 0 ) );

		if ( $group_id && function_exists( 'groups_get_groupmeta' ) ) {
			$force_group_only = (bool) groups_get_groupmeta( $group_id, 'ca_group_only', true );
			if ( $force_group_only ) {
				$visibility = 'group_only';
			}
		}

		$settings    = Community_Auctions_Settings::get_settings();
		$post_status = ! empty( $settings['admin_approval'] ) ? 'ca_pending' : 'ca_live';

		$auction_id = wp_insert_post(
			array(
				'post_type'    => 'auction',
				'post_title'   => $title,
				'post_content' => $description,
				'post_status'  => $post_status,
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $auction_id ) ) {
			return;
		}

		update_post_meta( $auction_id, 'ca_start_at', $start_at );
		update_post_meta( $auction_id, 'ca_end_at', $end_at );
		update_post_meta( $auction_id, 'ca_start_price', $start_price );
		update_post_meta( $auction_id, 'ca_reserve_price', $reserve_price );
		update_post_meta( $auction_id, 'ca_min_increment', $min_increment );
		update_post_meta( $auction_id, 'ca_visibility', $visibility );
		update_post_meta( $auction_id, 'ca_proxy_enabled', $proxy_enabled );

		if ( $group_id ) {
			update_post_meta( $auction_id, 'ca_group_id', $group_id );
		}

		// Handle Buy It Now fields.
		if ( Community_Auctions_Buy_Now::is_enabled_globally() ) {
			$buy_now_enabled = ! empty( $_POST['ca_buy_now_enabled'] ) ? 1 : 0;
			$buy_now_price   = floatval( wp_unslash( $_POST['ca_buy_now_price'] ?? 0 ) );

			update_post_meta( $auction_id, 'ca_buy_now_enabled', $buy_now_enabled );
			if ( $buy_now_enabled && $buy_now_price > 0 ) {
				update_post_meta( $auction_id, 'ca_buy_now_price', $buy_now_price );
			}
		}

		// Handle category assignment.
		if ( ! empty( $_POST['ca_categories'] ) && is_array( $_POST['ca_categories'] ) ) {
			$category_ids = array_map( 'absint', $_POST['ca_categories'] );
			Community_Auctions_Taxonomy::set_auction_categories( $auction_id, $category_ids );
		} elseif ( ! empty( $_POST['ca_category'] ) ) {
			$category_id = absint( $_POST['ca_category'] );
			if ( $category_id ) {
				Community_Auctions_Taxonomy::set_auction_categories( $auction_id, array( $category_id ) );
			}
		}

		// Handle gallery images.
		if ( ! empty( $_POST['ca_gallery_ids'] ) && is_array( $_POST['ca_gallery_ids'] ) ) {
			$gallery_ids = array_map( 'absint', $_POST['ca_gallery_ids'] );
			Community_Auctions_Image_Gallery::set_gallery_ids( $auction_id, $gallery_ids );

			// Set first image as featured if no featured image.
			if ( ! empty( $gallery_ids[0] ) && ! has_post_thumbnail( $auction_id ) ) {
				set_post_thumbnail( $auction_id, $gallery_ids[0] );
			}
		}

		do_action( 'community_auctions/auction_created', $auction_id, get_current_user_id() );

		wp_safe_redirect( get_permalink( $auction_id ) );
		exit;
	}

	/**
	 * Check if payment provider is selected.
	 *
	 * @return bool True if provider is configured.
	 */
	private static function is_payment_provider_selected() {
		$settings = Community_Auctions_Settings::get_settings();
		$provider = $settings['payment_provider'] ?? '';

		if ( empty( $provider ) ) {
			return false;
		}

		if ( 'woocommerce' === $provider ) {
			return Community_Auctions_Payment_WooCommerce::is_available();
		}

		if ( 'fluentcart' === $provider ) {
			return Community_Auctions_Payment_FluentCart::is_available();
		}

		return false;
	}
}
