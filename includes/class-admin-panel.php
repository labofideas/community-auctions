<?php
/**
 * Admin Panel with tabbed settings interface.
 *
 * @package CommunityAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Admin_Panel
 *
 * Provides a professional admin interface with tabs.
 */
final class Community_Auctions_Admin_Panel {

	/**
	 * Menu slug.
	 */
	const MENU_SLUG = 'community-auctions';

	/**
	 * Option key for settings.
	 */
	const OPTION_KEY = 'community_auctions_settings';

	/**
	 * Available tabs.
	 *
	 * @var array
	 */
	private static $tabs = array();

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_notices' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_ca_clear_cache', array( __CLASS__, 'ajax_clear_cache' ) );
		add_action( 'wp_ajax_ca_recalculate_counters', array( __CLASS__, 'ajax_recalculate_counters' ) );
	}

	/**
	 * Get tabs configuration.
	 *
	 * @return array Tabs configuration.
	 */
	private static function get_tabs() {
		if ( empty( self::$tabs ) ) {
			self::$tabs = array(
				'dashboard' => array(
					'title' => __( 'Dashboard', 'community-auctions' ),
					'icon'  => 'dashicons-chart-area',
				),
				'general'   => array(
					'title' => __( 'General', 'community-auctions' ),
					'icon'  => 'dashicons-admin-generic',
				),
				'bidding'   => array(
					'title' => __( 'Bidding', 'community-auctions' ),
					'icon'  => 'dashicons-money-alt',
				),
				'payments'  => array(
					'title' => __( 'Payments', 'community-auctions' ),
					'icon'  => 'dashicons-cart',
				),
				'fees'      => array(
					'title' => __( 'Fees', 'community-auctions' ),
					'icon'  => 'dashicons-calculator',
				),
				'currency'  => array(
					'title' => __( 'Currency', 'community-auctions' ),
					'icon'  => 'dashicons-money',
				),
				'emails'    => array(
					'title' => __( 'Emails', 'community-auctions' ),
					'icon'  => 'dashicons-email',
				),
				'advanced'  => array(
					'title' => __( 'Advanced', 'community-auctions' ),
					'icon'  => 'dashicons-admin-tools',
				),
			);
		}
		return self::$tabs;
	}

	/**
	 * Add admin menu.
	 */
	public static function add_menu() {
		$settings = Community_Auctions_Settings::get_settings();
		$plural   = $settings['label_plural'] ?? __( 'Auctions', 'community-auctions' );

		// Main menu.
		add_menu_page(
			__( 'Community Auctions', 'community-auctions' ),
			$plural,
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-hammer',
			30
		);

		// Submenu - Dashboard (same as parent).
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'community-auctions' ),
			__( 'Dashboard', 'community-auctions' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' )
		);

		// Submenu - All items.
		add_submenu_page(
			self::MENU_SLUG,
			/* translators: %s: plural label */
			sprintf( __( 'All %s', 'community-auctions' ), $plural ),
			/* translators: %s: plural label */
			sprintf( __( 'All %s', 'community-auctions' ), $plural ),
			'manage_options',
			'edit.php?post_type=auction'
		);

		// Submenu - Add New.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Add New', 'community-auctions' ),
			__( 'Add New', 'community-auctions' ),
			'manage_options',
			'post-new.php?post_type=auction'
		);

		// Submenu - Categories.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Categories', 'community-auctions' ),
			__( 'Categories', 'community-auctions' ),
			'manage_options',
			'edit-tags.php?taxonomy=auction_category&post_type=auction'
		);

		// Submenu - Settings.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'community-auctions' ),
			__( 'Settings', 'community-auctions' ),
			'manage_options',
			self::MENU_SLUG . '-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public static function register_settings() {
		register_setting(
			'community_auctions_settings',
			self::OPTION_KEY,
			array( __CLASS__, 'sanitize_settings' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, self::MENU_SLUG ) === false ) {
			return;
		}

		wp_enqueue_style(
			'ca-admin-panel',
			plugins_url( 'assets/css/admin-panel.css', dirname( __FILE__ ) ),
			array(),
			Community_Auctions_Plugin::VERSION
		);

		wp_enqueue_script(
			'ca-admin-panel',
			plugins_url( 'assets/js/admin-panel.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			Community_Auctions_Plugin::VERSION,
			true
		);

		wp_localize_script(
			'ca-admin-panel',
			'CaAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ca_admin_nonce' ),
				'strings' => array(
					'clearing'      => __( 'Clearing...', 'community-auctions' ),
					'cleared'       => __( 'Cache cleared!', 'community-auctions' ),
					'recalculating' => __( 'Recalculating...', 'community-auctions' ),
					'recalculated'  => __( 'Counters recalculated!', 'community-auctions' ),
					'error'         => __( 'An error occurred.', 'community-auctions' ),
				),
			)
		);
	}

	/**
	 * Render main dashboard page.
	 */
	public static function render_page() {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
		$tabs        = self::get_tabs();

		if ( ! isset( $tabs[ $current_tab ] ) ) {
			$current_tab = 'dashboard';
		}
		?>
		<div class="wrap ca-admin-wrap">
			<div class="ca-admin-header">
				<h1>
					<span class="dashicons dashicons-hammer"></span>
					<?php esc_html_e( 'Community Auctions', 'community-auctions' ); ?>
				</h1>
				<span class="ca-version">v<?php echo esc_html( Community_Auctions_Plugin::VERSION ); ?></span>
			</div>

			<nav class="ca-admin-tabs">
				<?php foreach ( $tabs as $tab_key => $tab ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=' . $tab_key ) ); ?>"
					   class="ca-tab <?php echo esc_attr( $current_tab === $tab_key ? 'ca-tab-active' : '' ); ?>">
						<span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
						<?php echo esc_html( $tab['title'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="ca-admin-content">
				<?php
				$method = 'render_tab_' . $current_tab;
				if ( method_exists( __CLASS__, $method ) ) {
					self::$method();
				} else {
					self::render_tab_dashboard();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings page (redirect to main with settings tab).
	 */
	public static function render_settings_page() {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=general' ) );
		exit;
	}

	/**
	 * Render Dashboard tab.
	 */
	private static function render_tab_dashboard() {
		$stats = self::get_stats();
		?>
		<div class="ca-dashboard">
			<div class="ca-stats-grid">
				<div class="ca-stat-card ca-stat-live">
					<div class="ca-stat-icon">
						<span class="dashicons dashicons-megaphone"></span>
					</div>
					<div class="ca-stat-content">
						<span class="ca-stat-number"><?php echo esc_html( $stats['live'] ); ?></span>
						<span class="ca-stat-label"><?php esc_html_e( 'Live Auctions', 'community-auctions' ); ?></span>
					</div>
				</div>

				<div class="ca-stat-card ca-stat-pending">
					<div class="ca-stat-icon">
						<span class="dashicons dashicons-clock"></span>
					</div>
					<div class="ca-stat-content">
						<span class="ca-stat-number"><?php echo esc_html( $stats['pending'] ); ?></span>
						<span class="ca-stat-label"><?php esc_html_e( 'Pending Approval', 'community-auctions' ); ?></span>
					</div>
				</div>

				<div class="ca-stat-card ca-stat-ended">
					<div class="ca-stat-icon">
						<span class="dashicons dashicons-flag"></span>
					</div>
					<div class="ca-stat-content">
						<span class="ca-stat-number"><?php echo esc_html( $stats['ended'] ); ?></span>
						<span class="ca-stat-label"><?php esc_html_e( 'Ended', 'community-auctions' ); ?></span>
					</div>
				</div>

				<div class="ca-stat-card ca-stat-bids">
					<div class="ca-stat-icon">
						<span class="dashicons dashicons-money-alt"></span>
					</div>
					<div class="ca-stat-content">
						<span class="ca-stat-number"><?php echo esc_html( $stats['total_bids'] ); ?></span>
						<span class="ca-stat-label"><?php esc_html_e( 'Total Bids', 'community-auctions' ); ?></span>
					</div>
				</div>

				<div class="ca-stat-card ca-stat-bidders">
					<div class="ca-stat-icon">
						<span class="dashicons dashicons-groups"></span>
					</div>
					<div class="ca-stat-content">
						<span class="ca-stat-number"><?php echo esc_html( $stats['unique_bidders'] ); ?></span>
						<span class="ca-stat-label"><?php esc_html_e( 'Unique Bidders', 'community-auctions' ); ?></span>
					</div>
				</div>

				<div class="ca-stat-card ca-stat-payments">
					<div class="ca-stat-icon">
						<span class="dashicons dashicons-warning"></span>
					</div>
					<div class="ca-stat-content">
						<span class="ca-stat-number"><?php echo esc_html( $stats['pending_payments'] ); ?></span>
						<span class="ca-stat-label"><?php esc_html_e( 'Pending Payments', 'community-auctions' ); ?></span>
					</div>
				</div>
			</div>

			<div class="ca-dashboard-row">
				<div class="ca-dashboard-box ca-quick-actions">
					<h3><?php esc_html_e( 'Quick Actions', 'community-auctions' ); ?></h3>
					<div class="ca-actions-list">
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=auction' ) ); ?>" class="ca-action-btn">
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e( 'Create Auction', 'community-auctions' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=auction&post_status=ca_pending' ) ); ?>" class="ca-action-btn">
							<span class="dashicons dashicons-visibility"></span>
							<?php esc_html_e( 'Review Pending', 'community-auctions' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=auction_category&post_type=auction' ) ); ?>" class="ca-action-btn">
							<span class="dashicons dashicons-category"></span>
							<?php esc_html_e( 'Manage Categories', 'community-auctions' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&tab=general' ) ); ?>" class="ca-action-btn">
							<span class="dashicons dashicons-admin-settings"></span>
							<?php esc_html_e( 'Settings', 'community-auctions' ); ?>
						</a>
					</div>
				</div>

				<div class="ca-dashboard-box ca-system-status">
					<h3><?php esc_html_e( 'System Status', 'community-auctions' ); ?></h3>
					<table class="ca-status-table">
						<tr>
							<td><?php esc_html_e( 'Payment Provider', 'community-auctions' ); ?></td>
							<td><?php echo esc_html( self::get_payment_provider_label() ); ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'WooCommerce', 'community-auctions' ); ?></td>
							<td><?php echo Community_Auctions_Plugin::is_woocommerce_active() ? '<span class="ca-status-yes">Active</span>' : '<span class="ca-status-no">Inactive</span>'; ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'FluentCart', 'community-auctions' ); ?></td>
							<td><?php echo Community_Auctions_Plugin::is_fluentcart_active() ? '<span class="ca-status-yes">Active</span>' : '<span class="ca-status-no">Inactive</span>'; ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'BuddyPress', 'community-auctions' ); ?></td>
							<td><?php echo class_exists( 'BuddyPress' ) ? '<span class="ca-status-yes">Active</span>' : '<span class="ca-status-no">Inactive</span>'; ?></td>
						</tr>
						<tr>
							<td><?php esc_html_e( 'Object Cache', 'community-auctions' ); ?></td>
							<td><?php echo wp_using_ext_object_cache() ? '<span class="ca-status-yes">Enabled</span>' : '<span class="ca-status-no">Disabled</span>'; ?></td>
						</tr>
					</table>
				</div>
			</div>

			<?php if ( ! empty( $stats['recent_auctions'] ) ) : ?>
			<div class="ca-dashboard-box ca-recent-auctions">
				<h3><?php esc_html_e( 'Recent Auctions', 'community-auctions' ); ?></h3>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Title', 'community-auctions' ); ?></th>
							<th><?php esc_html_e( 'Status', 'community-auctions' ); ?></th>
							<th><?php esc_html_e( 'Current Bid', 'community-auctions' ); ?></th>
							<th><?php esc_html_e( 'Bids', 'community-auctions' ); ?></th>
							<th><?php esc_html_e( 'Ends', 'community-auctions' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $stats['recent_auctions'] as $auction ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( $auction->ID ) ); ?>">
									<?php echo esc_html( $auction->post_title ); ?>
								</a>
							</td>
							<td><?php echo esc_html( self::get_status_label( $auction->post_status ) ); ?></td>
							<td><?php echo esc_html( Community_Auctions_Currency::format( get_post_meta( $auction->ID, 'ca_current_bid', true ) ?: get_post_meta( $auction->ID, 'ca_start_price', true ) ) ); ?></td>
							<td><?php echo esc_html( get_post_meta( $auction->ID, 'ca_bid_count', true ) ?: 0 ); ?></td>
							<td><?php echo esc_html( get_post_meta( $auction->ID, 'ca_end_at', true ) ?: '—' ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render General settings tab.
	 */
	private static function render_tab_general() {
		$settings = Community_Auctions_Settings::get_settings();
		?>
		<form method="post" action="options.php" class="ca-settings-form">
			<?php settings_fields( 'community_auctions_settings' ); ?>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Labels & URLs', 'community-auctions' ); ?></h2>
				<p class="ca-section-desc"><?php esc_html_e( 'Customize how auctions are named throughout your site.', 'community-auctions' ); ?></p>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Singular Name', 'community-auctions' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[label_singular]" value="<?php echo esc_attr( $settings['label_singular'] ?? 'Auction' ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'e.g., Auction, Listing, Item, Lot', 'community-auctions' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Plural Name', 'community-auctions' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[label_plural]" value="<?php echo esc_attr( $settings['label_plural'] ?? 'Auctions' ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'e.g., Auctions, Listings, Items, Lots', 'community-auctions' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'URL Slug', 'community-auctions' ); ?></th>
						<td>
							<code><?php echo esc_html( home_url( '/' ) ); ?></code>
							<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[url_slug]" value="<?php echo esc_attr( $settings['url_slug'] ?? 'auctions' ); ?>" class="regular-text" style="width: 150px;" />
							<code>/</code>
							<p class="description"><?php esc_html_e( 'The URL base for auction pages. Use lowercase letters, numbers, and hyphens only.', 'community-auctions' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Role Permissions', 'community-auctions' ); ?></h2>
				<p class="ca-section-desc"><?php esc_html_e( 'Control which user roles can create auctions and place bids.', 'community-auctions' ); ?></p>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Auction Creation Roles', 'community-auctions' ); ?></th>
						<td>
							<?php self::render_roles_checkboxes( 'allowed_roles_create', $settings ); ?>
							<p class="description"><?php esc_html_e( 'Users with these roles can create new auctions.', 'community-auctions' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Bidding Roles', 'community-auctions' ); ?></th>
						<td>
							<?php self::render_roles_checkboxes( 'allowed_roles_bid', $settings ); ?>
							<p class="description"><?php esc_html_e( 'Users with these roles can place bids on auctions.', 'community-auctions' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Auction Settings', 'community-auctions' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Require Admin Approval', 'community-auctions' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[admin_approval]" value="1" <?php checked( ! empty( $settings['admin_approval'] ) ); ?> />
								<?php esc_html_e( 'Auctions must be approved before going live', 'community-auctions' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default Visibility', 'community-auctions' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[group_visibility_default]">
								<option value="public" <?php selected( $settings['group_visibility_default'] ?? 'public', 'public' ); ?>><?php esc_html_e( 'Public', 'community-auctions' ); ?></option>
								<option value="group_only" <?php selected( $settings['group_visibility_default'] ?? '', 'group_only' ); ?>><?php esc_html_e( 'Group Only', 'community-auctions' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Proxy Bidding Default', 'community-auctions' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[proxy_default]" value="1" <?php checked( ! empty( $settings['proxy_default'] ) ); ?> />
								<?php esc_html_e( 'Enable proxy bidding by default for new auctions', 'community-auctions' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render Bidding settings tab.
	 */
	private static function render_tab_bidding() {
		$settings = Community_Auctions_Settings::get_settings();
		?>
		<form method="post" action="options.php" class="ca-settings-form">
			<?php settings_fields( 'community_auctions_settings' ); ?>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Bidding Rules', 'community-auctions' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Block Seller Bidding', 'community-auctions' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[block_seller_bidding]" value="1" <?php checked( ! empty( $settings['block_seller_bidding'] ) ); ?> />
								<?php esc_html_e( 'Prevent sellers from bidding on their own auctions', 'community-auctions' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Prevent Duplicate Bids', 'community-auctions' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[prevent_duplicate_highest]" value="1" <?php checked( ! empty( $settings['prevent_duplicate_highest'] ) ); ?> />
								<?php esc_html_e( 'Prevent users from bidding when already the highest bidder', 'community-auctions' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Maximum Bid Limit', 'community-auctions' ); ?></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[max_bid_limit]" value="<?php echo esc_attr( $settings['max_bid_limit'] ?? 0 ); ?>" min="0" step="0.01" class="small-text" />
							<p class="description"><?php esc_html_e( 'Maximum allowed bid amount. Set to 0 for no limit.', 'community-auctions' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Anti-Sniping', 'community-auctions' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Extension Minutes', 'community-auctions' ); ?></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[anti_sniping_minutes]" value="<?php echo esc_attr( $settings['anti_sniping_minutes'] ?? 0 ); ?>" min="0" step="1" class="small-text" />
							<p class="description"><?php esc_html_e( 'Extend auction by this many minutes if a bid is placed in the last few minutes. Set to 0 to disable.', 'community-auctions' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Real-time Updates', 'community-auctions' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Real-time Updates', 'community-auctions' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[realtime_enabled]" value="1" <?php checked( ! empty( $settings['realtime_enabled'] ) ); ?> />
								<?php esc_html_e( 'Auto-refresh bid information on auction pages', 'community-auctions' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Poll Interval', 'community-auctions' ); ?></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[realtime_poll_interval]" value="<?php echo esc_attr( $settings['realtime_poll_interval'] ?? 15 ); ?>" min="5" step="1" class="small-text" />
							<span><?php esc_html_e( 'seconds', 'community-auctions' ); ?></span>
							<p class="description"><?php esc_html_e( 'Minimum 5 seconds. Lower values increase server load.', 'community-auctions' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Buy It Now', 'community-auctions' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Buy It Now', 'community-auctions' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[buy_now_enabled]" value="1" <?php checked( ! empty( $settings['buy_now_enabled'] ) ); ?> />
								<?php esc_html_e( 'Allow sellers to set a Buy It Now price', 'community-auctions' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Buy It Now Ends Auction', 'community-auctions' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[buy_now_ends_auction]" value="1" <?php checked( ! empty( $settings['buy_now_ends_auction'] ) ); ?> />
								<?php esc_html_e( 'Immediately end auction when someone uses Buy It Now', 'community-auctions' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render Payments settings tab.
	 */
	private static function render_tab_payments() {
		$settings   = Community_Auctions_Settings::get_settings();
		$has_woo    = Community_Auctions_Plugin::is_woocommerce_active();
		$has_fluent = Community_Auctions_Plugin::is_fluentcart_active();
		?>
		<form method="post" action="options.php" class="ca-settings-form">
			<?php settings_fields( 'community_auctions_settings' ); ?>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Payment Provider', 'community-auctions' ); ?></h2>

				<?php if ( ! $has_woo && ! $has_fluent ) : ?>
					<div class="ca-notice ca-notice-warning">
						<p><?php esc_html_e( 'No payment provider detected. Please install WooCommerce or FluentCart to process payments.', 'community-auctions' ); ?></p>
					</div>
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Select Provider', 'community-auctions' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[payment_provider]">
								<option value=""><?php esc_html_e( '— Select Provider —', 'community-auctions' ); ?></option>
								<?php if ( $has_woo ) : ?>
									<option value="woocommerce" <?php selected( $settings['payment_provider'] ?? '', 'woocommerce' ); ?>><?php esc_html_e( 'WooCommerce', 'community-auctions' ); ?></option>
								<?php endif; ?>
								<?php if ( $has_fluent ) : ?>
									<option value="fluentcart" <?php selected( $settings['payment_provider'] ?? '', 'fluentcart' ); ?>><?php esc_html_e( 'FluentCart', 'community-auctions' ); ?></option>
								<?php endif; ?>
							</select>
						</td>
					</tr>
				</table>
			</div>

			<?php if ( $has_woo ) : ?>
			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'WooCommerce Settings', 'community-auctions' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Cash on Delivery', 'community-auctions' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enable_cod]" value="1" <?php checked( ! empty( $settings['enable_cod'] ) ); ?> />
								<?php esc_html_e( 'Allow Cash on Delivery for auction orders', 'community-auctions' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>
			<?php endif; ?>

			<?php if ( $has_fluent ) : ?>
			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'FluentCart Settings', 'community-auctions' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Webhook Secret', 'community-auctions' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[fluentcart_webhook_secret]" value="<?php echo esc_attr( $settings['fluentcart_webhook_secret'] ?? '' ); ?>" class="regular-text" />
							<p class="description">
								<?php esc_html_e( 'Webhook URL:', 'community-auctions' ); ?>
								<code><?php echo esc_html( rest_url( 'community-auctions/v1/fluentcart/webhook' ) ); ?></code>
							</p>
						</td>
					</tr>
				</table>
			</div>
			<?php endif; ?>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Payment Reminders', 'community-auctions' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Reminder Delay', 'community-auctions' ); ?></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[payment_reminder_hours]" value="<?php echo esc_attr( $settings['payment_reminder_hours'] ?? 24 ); ?>" min="1" step="1" class="small-text" />
							<span><?php esc_html_e( 'hours', 'community-auctions' ); ?></span>
							<p class="description"><?php esc_html_e( 'Send payment reminder after this many hours if payment is not received.', 'community-auctions' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render Fees settings tab.
	 */
	private static function render_tab_fees() {
		$settings = Community_Auctions_Settings::get_settings();
		?>
		<form method="post" action="options.php" class="ca-settings-form">
			<?php settings_fields( 'community_auctions_settings' ); ?>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Listing Fee', 'community-auctions' ); ?></h2>
				<p class="ca-section-desc"><?php esc_html_e( 'Charge sellers a fee when they list an auction.', 'community-auctions' ); ?></p>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Listing Fee', 'community-auctions' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[listing_fee_enabled]" value="1" <?php checked( ! empty( $settings['listing_fee_enabled'] ) ); ?> />
								<?php esc_html_e( 'Charge a fee for listing auctions', 'community-auctions' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Fee Type', 'community-auctions' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[listing_fee_mode]">
								<option value="flat" <?php selected( $settings['listing_fee_mode'] ?? 'flat', 'flat' ); ?>><?php esc_html_e( 'Flat Amount', 'community-auctions' ); ?></option>
								<option value="percent" <?php selected( $settings['listing_fee_mode'] ?? '', 'percent' ); ?>><?php esc_html_e( 'Percentage', 'community-auctions' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Fee Amount', 'community-auctions' ); ?></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[listing_fee_amount]" value="<?php echo esc_attr( $settings['listing_fee_amount'] ?? 0 ); ?>" min="0" step="0.01" class="small-text" />
						</td>
					</tr>
				</table>
			</div>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Success Fee', 'community-auctions' ); ?></h2>
				<p class="ca-section-desc"><?php esc_html_e( 'Charge sellers a fee when their auction sells successfully.', 'community-auctions' ); ?></p>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Success Fee', 'community-auctions' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[success_fee_enabled]" value="1" <?php checked( ! empty( $settings['success_fee_enabled'] ) ); ?> />
								<?php esc_html_e( 'Charge a fee on successful sales', 'community-auctions' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Fee Type', 'community-auctions' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[success_fee_mode]">
								<option value="flat" <?php selected( $settings['success_fee_mode'] ?? 'flat', 'flat' ); ?>><?php esc_html_e( 'Flat Amount', 'community-auctions' ); ?></option>
								<option value="percent" <?php selected( $settings['success_fee_mode'] ?? '', 'percent' ); ?>><?php esc_html_e( 'Percentage', 'community-auctions' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Fee Amount', 'community-auctions' ); ?></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[success_fee_amount]" value="<?php echo esc_attr( $settings['success_fee_amount'] ?? 0 ); ?>" min="0" step="0.01" class="small-text" />
						</td>
					</tr>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render Currency settings tab.
	 */
	private static function render_tab_currency() {
		$settings   = Community_Auctions_Settings::get_settings();
		$currencies = Community_Auctions_Currency::get_currencies();
		?>
		<form method="post" action="options.php" class="ca-settings-form">
			<?php settings_fields( 'community_auctions_settings' ); ?>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Currency Settings', 'community-auctions' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Currency', 'community-auctions' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[currency_code]">
								<?php foreach ( $currencies as $code => $currency ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $settings['currency_code'] ?? 'USD', $code ); ?>>
										<?php echo esc_html( $currency['name'] . ' (' . $currency['symbol'] . ')' ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Symbol Position', 'community-auctions' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[currency_position]">
								<option value="before" <?php selected( $settings['currency_position'] ?? 'before', 'before' ); ?>><?php esc_html_e( 'Before amount ($100)', 'community-auctions' ); ?></option>
								<option value="after" <?php selected( $settings['currency_position'] ?? '', 'after' ); ?>><?php esc_html_e( 'After amount (100$)', 'community-auctions' ); ?></option>
								<option value="before_space" <?php selected( $settings['currency_position'] ?? '', 'before_space' ); ?>><?php esc_html_e( 'Before with space ($ 100)', 'community-auctions' ); ?></option>
								<option value="after_space" <?php selected( $settings['currency_position'] ?? '', 'after_space' ); ?>><?php esc_html_e( 'After with space (100 $)', 'community-auctions' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Decimal Places', 'community-auctions' ); ?></th>
						<td>
							<input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[currency_decimals]" value="<?php echo esc_attr( $settings['currency_decimals'] ?? 2 ); ?>" min="0" max="4" step="1" class="small-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Decimal Separator', 'community-auctions' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[currency_decimal_sep]" value="<?php echo esc_attr( $settings['currency_decimal_sep'] ?? '.' ); ?>" class="small-text" maxlength="1" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Thousands Separator', 'community-auctions' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[currency_thousands_sep]" value="<?php echo esc_attr( $settings['currency_thousands_sep'] ?? ',' ); ?>" class="small-text" maxlength="1" />
						</td>
					</tr>
				</table>
			</div>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Preview', 'community-auctions' ); ?></h2>
				<p class="ca-currency-preview">
					<strong><?php esc_html_e( 'Example:', 'community-auctions' ); ?></strong>
					<?php echo esc_html( Community_Auctions_Currency::format( 1234.56 ) ); ?>
				</p>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render Emails settings tab.
	 */
	private static function render_tab_emails() {
		$settings = Community_Auctions_Settings::get_settings();
		?>
		<form method="post" action="options.php" class="ca-settings-form">
			<?php settings_fields( 'community_auctions_settings' ); ?>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Email Settings', 'community-auctions' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Subject Prefix', 'community-auctions' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email_subject_prefix]" value="<?php echo esc_attr( $settings['email_subject_prefix'] ?? '' ); ?>" class="regular-text" placeholder="[Auctions]" />
							<p class="description"><?php esc_html_e( 'Prefix added to all email subjects.', 'community-auctions' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'From Name', 'community-auctions' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email_from_name]" value="<?php echo esc_attr( $settings['email_from_name'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'From Email', 'community-auctions' ); ?></th>
						<td>
							<input type="email" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email_from_address]" value="<?php echo esc_attr( $settings['email_from_address'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'admin_email' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Footer Text', 'community-auctions' ); ?></th>
						<td>
							<textarea name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email_footer_text]" rows="3" class="large-text"><?php echo esc_textarea( $settings['email_footer_text'] ?? '' ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Text shown at the bottom of all emails.', 'community-auctions' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Email Templates', 'community-auctions' ); ?></h2>
				<p class="ca-section-desc">
					<?php esc_html_e( 'Email templates are located in:', 'community-auctions' ); ?>
					<code>wp-content/plugins/community-auctions/templates/emails/</code>
				</p>
				<p class="ca-section-desc">
					<?php esc_html_e( 'To customize, copy templates to:', 'community-auctions' ); ?>
					<code>wp-content/themes/your-theme/community-auctions/emails/</code>
				</p>

				<table class="wp-list-table widefat fixed striped ca-templates-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Template', 'community-auctions' ); ?></th>
							<th><?php esc_html_e( 'Description', 'community-auctions' ); ?></th>
							<th><?php esc_html_e( 'File', 'community-auctions' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Outbid', 'community-auctions' ); ?></strong></td>
							<td><?php esc_html_e( 'Sent when a user is outbid', 'community-auctions' ); ?></td>
							<td><code>outbid.php</code></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Won', 'community-auctions' ); ?></strong></td>
							<td><?php esc_html_e( 'Sent when a user wins an auction', 'community-auctions' ); ?></td>
							<td><code>won.php</code></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Seller Sold', 'community-auctions' ); ?></strong></td>
							<td><?php esc_html_e( 'Sent to seller when auction sells', 'community-auctions' ); ?></td>
							<td><code>seller-sold.php</code></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Payment Reminder', 'community-auctions' ); ?></strong></td>
							<td><?php esc_html_e( 'Reminder to complete payment', 'community-auctions' ); ?></td>
							<td><code>payment-reminder.php</code></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Watched Ending', 'community-auctions' ); ?></strong></td>
							<td><?php esc_html_e( 'Watched auction ending soon', 'community-auctions' ); ?></td>
							<td><code>watched-ending.php</code></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Auction Starting', 'community-auctions' ); ?></strong></td>
							<td><?php esc_html_e( 'Watched auction starting soon', 'community-auctions' ); ?></td>
							<td><code>auction-starting.php</code></td>
						</tr>
					</tbody>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render Advanced settings tab.
	 */
	private static function render_tab_advanced() {
		?>
		<div class="ca-settings-form">
			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Cache Management', 'community-auctions' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Clear Cache', 'community-auctions' ); ?></th>
						<td>
							<button type="button" class="button" id="ca-clear-cache">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Clear All Caches', 'community-auctions' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Clear all auction-related caches.', 'community-auctions' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Recalculate Counters', 'community-auctions' ); ?></th>
						<td>
							<button type="button" class="button" id="ca-recalculate-counters">
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Recalculate All Counters', 'community-auctions' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Recalculate bid counts and statistics for all auctions.', 'community-auctions' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Database', 'community-auctions' ); ?></h2>

				<?php
				global $wpdb;
				$bids_table      = $wpdb->prefix . 'ca_bids';
				$watchlist_table = $wpdb->prefix . 'ca_watchlist';
				$bids_exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bids_table ) ) === $bids_table;
				$watchlist_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $watchlist_table ) ) === $watchlist_table;
				?>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Bids Table', 'community-auctions' ); ?></th>
						<td>
							<code><?php echo esc_html( $bids_table ); ?></code>
							<?php echo wp_kses_post( $bids_exists ? '<span class="ca-status-yes">✓ Exists</span>' : '<span class="ca-status-no">✗ Missing</span>' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Watchlist Table', 'community-auctions' ); ?></th>
						<td>
							<code><?php echo esc_html( $watchlist_table ); ?></code>
							<?php echo wp_kses_post( $watchlist_exists ? '<span class="ca-status-yes">✓ Exists</span>' : '<span class="ca-status-no">✗ Missing</span>' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'DB Version', 'community-auctions' ); ?></th>
						<td>
							<code><?php echo esc_html( get_option( 'community_auctions_db_version', '1.0.0' ) ); ?></code>
						</td>
					</tr>
				</table>
			</div>

			<div class="ca-settings-section">
				<h2><?php esc_html_e( 'Shortcodes Reference', 'community-auctions' ); ?></h2>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Shortcode', 'community-auctions' ); ?></th>
							<th><?php esc_html_e( 'Description', 'community-auctions' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>[community_auctions_list]</code></td>
							<td><?php esc_html_e( 'Display a list of live auctions', 'community-auctions' ); ?></td>
						</tr>
						<tr>
							<td><code>[community_auctions_search]</code></td>
							<td><?php esc_html_e( 'Search and filter auctions', 'community-auctions' ); ?></td>
						</tr>
						<tr>
							<td><code>[community_auctions_upcoming]</code></td>
							<td><?php esc_html_e( 'Display upcoming auctions', 'community-auctions' ); ?></td>
						</tr>
						<tr>
							<td><code>[community_auction_form]</code></td>
							<td><?php esc_html_e( 'Frontend auction submission form', 'community-auctions' ); ?></td>
						</tr>
						<tr>
							<td><code>[community_auction_watchlist]</code></td>
							<td><?php esc_html_e( 'User\'s watchlist', 'community-auctions' ); ?></td>
						</tr>
						<tr>
							<td><code>[community_auction_seller_dashboard]</code></td>
							<td><?php esc_html_e( 'Seller dashboard', 'community-auctions' ); ?></td>
						</tr>
						<tr>
							<td><code>[community_auction_buyer_dashboard]</code></td>
							<td><?php esc_html_e( 'Buyer dashboard', 'community-auctions' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public static function sanitize_settings( $input ) {
		// Delegate to existing settings class.
		return Community_Auctions_Settings::sanitize_settings( $input );
	}

	/**
	 * Show admin notices.
	 */
	public static function show_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Community_Auctions_Settings::get_settings();

		// Check if payment provider is configured.
		$has_woo    = Community_Auctions_Plugin::is_woocommerce_active();
		$has_fluent = Community_Auctions_Plugin::is_fluentcart_active();

		if ( ( $has_woo || $has_fluent ) && empty( $settings['payment_provider'] ) ) {
			$screen = get_current_screen();
			if ( $screen && strpos( $screen->id, self::MENU_SLUG ) !== false ) {
				echo '<div class="notice notice-warning"><p>';
				echo '<strong>' . esc_html__( 'Community Auctions:', 'community-auctions' ) . '</strong> ';
				echo esc_html__( 'Please select a payment provider in the Payments tab.', 'community-auctions' );
				echo '</p></div>';
			}
		}
	}

	/**
	 * AJAX: Clear cache.
	 */
	public static function ajax_clear_cache() {
		check_ajax_referer( 'ca_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'community-auctions' ) ) );
		}

		Community_Auctions_Performance::clear_all_caches();

		wp_send_json_success( array( 'message' => __( 'Cache cleared successfully.', 'community-auctions' ) ) );
	}

	/**
	 * AJAX: Recalculate counters.
	 */
	public static function ajax_recalculate_counters() {
		check_ajax_referer( 'ca_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'community-auctions' ) ) );
		}

		$count = Community_Auctions_Performance::recalculate_all_counters();

		wp_send_json_success(
			array(
				/* translators: %d: number of auctions */
				'message' => sprintf( __( 'Counters recalculated for %d auctions.', 'community-auctions' ), $count ),
			)
		);
	}

	/**
	 * Render roles checkboxes.
	 *
	 * @param string $key      Setting key.
	 * @param array  $settings Current settings.
	 */
	private static function render_roles_checkboxes( $key, $settings ) {
		$selected = isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? $settings[ $key ] : array();
		$roles    = wp_roles();

		if ( ! $roles ) {
			echo '<p>' . esc_html__( 'No roles available.', 'community-auctions' ) . '</p>';
			return;
		}

		echo '<div class="ca-roles-grid">';
		foreach ( $roles->roles as $role_key => $role_data ) {
			echo '<label class="ca-role-checkbox">';
			echo '<input type="checkbox" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . '][]" value="' . esc_attr( $role_key ) . '" ' . checked( in_array( $role_key, $selected, true ), true, false ) . ' /> ';
			echo esc_html( $role_data['name'] );
			echo '</label>';
		}
		echo '</div>';
	}

	/**
	 * Get dashboard stats.
	 *
	 * @return array Stats data.
	 */
	private static function get_stats() {
		global $wpdb;

		$counts = wp_count_posts( 'auction' );

		$bids_table = $wpdb->prefix . 'ca_bids';
		$total_bids = 0;
		$unique_bidders = 0;

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $bids_table ) ) === $bids_table ) {
			$total_bids     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$bids_table}" );
			$unique_bidders = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$bids_table}" );
		}

		// Pending payments count.
		$pending_payments = new WP_Query(
			array(
				'post_type'      => 'auction',
				'post_status'    => array( 'ca_ended', 'ca_closed' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => 'ca_highest_bidder',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'ca_paid_at',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		// Recent auctions.
		$recent = get_posts(
			array(
				'post_type'      => 'auction',
				'post_status'    => array( 'publish', 'ca_pending', 'ca_live', 'ca_ended' ),
				'posts_per_page' => 5,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		return array(
			'live'             => isset( $counts->ca_live ) ? (int) $counts->ca_live : 0,
			'pending'          => isset( $counts->ca_pending ) ? (int) $counts->ca_pending : 0,
			'ended'            => ( isset( $counts->ca_ended ) ? (int) $counts->ca_ended : 0 ) + ( isset( $counts->ca_closed ) ? (int) $counts->ca_closed : 0 ),
			'total_bids'       => $total_bids,
			'unique_bidders'   => $unique_bidders,
			'pending_payments' => (int) $pending_payments->found_posts,
			'recent_auctions'  => $recent,
		);
	}

	/**
	 * Get payment provider label.
	 *
	 * @return string Provider label.
	 */
	private static function get_payment_provider_label() {
		$settings = Community_Auctions_Settings::get_settings();
		$provider = $settings['payment_provider'] ?? '';

		switch ( $provider ) {
			case 'woocommerce':
				return __( 'WooCommerce', 'community-auctions' );
			case 'fluentcart':
				return __( 'FluentCart', 'community-auctions' );
			default:
				return __( 'Not configured', 'community-auctions' );
		}
	}

	/**
	 * Get status label.
	 *
	 * @param string $status Post status.
	 * @return string Status label.
	 */
	private static function get_status_label( $status ) {
		$labels = array(
			'publish'    => __( 'Published', 'community-auctions' ),
			'ca_pending' => __( 'Pending', 'community-auctions' ),
			'ca_live'    => __( 'Live', 'community-auctions' ),
			'ca_ended'   => __( 'Ended', 'community-auctions' ),
			'ca_closed'  => __( 'Closed', 'community-auctions' ),
		);

		return $labels[ $status ] ?? $status;
	}
}
