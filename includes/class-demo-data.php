<?php
/**
 * Demo Data Importer - Creates sample data for plugin showcase.
 *
 * @package CommunityAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles demo data import for plugin showcase.
 */
class Community_Auctions_Demo_Data {

	/**
	 * Option key to track if demo data was imported.
	 */
	const IMPORTED_OPTION = 'community_auctions_demo_imported';

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_submenu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_notices', array( __CLASS__, 'show_import_notice' ) );
	}

	/**
	 * Add submenu page for demo data.
	 */
	public static function add_submenu() {
		add_submenu_page(
			'community-auctions',
			__( 'Demo Data', 'community-auctions' ),
			__( 'Demo Data', 'community-auctions' ),
			'manage_options',
			'ca-demo-data',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render demo data admin page.
	 */
	public static function render_page() {
		$imported = get_option( self::IMPORTED_OPTION, false );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Demo Data', 'community-auctions' ); ?></h1>

			<?php if ( $imported ) : ?>
				<div class="notice notice-info">
					<p>
						<strong><?php esc_html_e( 'Demo data has been imported.', 'community-auctions' ); ?></strong>
						<?php esc_html_e( 'You can remove it and import fresh data below.', 'community-auctions' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<div class="card" style="max-width: 600px; padding: 20px;">
				<h2><?php esc_html_e( 'What will be created?', 'community-auctions' ); ?></h2>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php esc_html_e( '6 Pages with shortcodes (Submit Auction, My Auctions, My Purchases, Watchlist, Search, Upcoming)', 'community-auctions' ); ?></li>
					<li><?php esc_html_e( '4 Auction categories (Electronics, Collectibles, Art & Antiques, Jewelry)', 'community-auctions' ); ?></li>
					<li><?php esc_html_e( '8 Sample auctions with different states:', 'community-auctions' ); ?>
						<ul style="list-style: circle; margin-left: 20px;">
							<li><?php esc_html_e( 'Live auctions with bids', 'community-auctions' ); ?></li>
							<li><?php esc_html_e( 'Ending soon auction (urgency testing)', 'community-auctions' ); ?></li>
							<li><?php esc_html_e( 'Buy Now enabled auction', 'community-auctions' ); ?></li>
							<li><?php esc_html_e( 'Upcoming auctions', 'community-auctions' ); ?></li>
							<li><?php esc_html_e( 'Ended auctions with winners & WooCommerce orders', 'community-auctions' ); ?></li>
						</ul>
					</li>
					<li><?php esc_html_e( 'Sample bids on live and ended auctions', 'community-auctions' ); ?></li>
					<li><?php esc_html_e( 'Watchlist entries for testing', 'community-auctions' ); ?></li>
					<li><?php esc_html_e( 'WooCommerce payment orders for won auctions', 'community-auctions' ); ?></li>
					<li><?php esc_html_e( 'Auctions menu item added to primary navigation', 'community-auctions' ); ?></li>
					<li><?php esc_html_e( 'BuddyPress activities for live auctions (if active)', 'community-auctions' ); ?></li>
				</ul>

				<form method="post" style="margin-top: 20px;">
					<?php wp_nonce_field( 'ca_demo_import', 'ca_demo_nonce' ); ?>

					<?php if ( $imported ) : ?>
						<button type="submit" name="ca_demo_action" value="remove" class="button" onclick="return confirm('<?php esc_attr_e( 'Remove all demo data? This cannot be undone.', 'community-auctions' ); ?>');">
							<?php esc_html_e( 'Remove Demo Data', 'community-auctions' ); ?>
						</button>
						<span style="margin: 0 10px;"><?php esc_html_e( 'or', 'community-auctions' ); ?></span>
					<?php endif; ?>

					<button type="submit" name="ca_demo_action" value="import" class="button button-primary">
						<?php echo $imported ? esc_html__( 'Re-import Demo Data', 'community-auctions' ) : esc_html__( 'Import Demo Data', 'community-auctions' ); ?>
					</button>
				</form>
			</div>

			<?php if ( $imported ) : ?>
				<div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
					<h2><?php esc_html_e( 'Quick Links', 'community-auctions' ); ?></h2>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/auctions/' ) ); ?>"><?php esc_html_e( 'View Auction Archive', 'community-auctions' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/submit-auction/' ) ); ?>"><?php esc_html_e( 'Submit Auction Page', 'community-auctions' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/my-auctions/' ) ); ?>"><?php esc_html_e( 'Seller Dashboard', 'community-auctions' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/my-purchases/' ) ); ?>"><?php esc_html_e( 'Buyer Dashboard', 'community-auctions' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/search-auctions/' ) ); ?>"><?php esc_html_e( 'Search Auctions', 'community-auctions' ); ?></a></li>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle import/remove actions.
	 */
	public static function handle_import() {
		if ( ! isset( $_POST['ca_demo_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['ca_demo_nonce'] ) ), 'ca_demo_import' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = isset( $_POST['ca_demo_action'] ) ? sanitize_text_field( $_POST['ca_demo_action'] ) : '';

		if ( 'import' === $action ) {
			self::remove_demo_data(); // Clean up first.
			self::import_demo_data();
			update_option( self::IMPORTED_OPTION, true );
			add_settings_error( 'ca_demo', 'imported', __( 'Demo data imported successfully!', 'community-auctions' ), 'success' );
		} elseif ( 'remove' === $action ) {
			self::remove_demo_data();
			delete_option( self::IMPORTED_OPTION );
			add_settings_error( 'ca_demo', 'removed', __( 'Demo data removed.', 'community-auctions' ), 'success' );
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( admin_url( 'admin.php?page=ca-demo-data&settings-updated=true' ) );
		exit;
	}

	/**
	 * Show admin notices after import.
	 */
	public static function show_import_notice() {
		$screen = get_current_screen();
		if ( $screen && strpos( $screen->id, 'ca-demo-data' ) !== false && isset( $_GET['settings-updated'] ) ) {
			settings_errors( 'ca_demo' );
		}
	}

	/**
	 * Import all demo data.
	 */
	public static function import_demo_data() {
		// Create categories first.
		$categories = self::create_categories();

		// Create pages with shortcodes.
		$pages = self::create_pages();

		// Create sample auctions.
		$auctions = self::create_auctions( $categories );

		// Create sample bids.
		self::create_bids( $auctions );

		// Create WooCommerce orders for won auctions.
		self::create_woocommerce_orders( $auctions );

		// Create watchlist entries for testing.
		self::create_watchlist_entries( $auctions );

		// Add pages to navigation menu.
		self::add_pages_to_menu( $pages );

		// Create BuddyPress activities for live auctions.
		self::create_buddypress_activities( $auctions );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Create auction categories.
	 *
	 * @return array Created category term IDs.
	 */
	private static function create_categories() {
		$categories = array(
			array(
				'name' => __( 'Electronics', 'community-auctions' ),
				'slug' => 'electronics',
				'desc' => __( 'Gadgets, devices, and tech items', 'community-auctions' ),
			),
			array(
				'name' => __( 'Collectibles', 'community-auctions' ),
				'slug' => 'collectibles',
				'desc' => __( 'Rare and vintage collectible items', 'community-auctions' ),
			),
			array(
				'name' => __( 'Art & Antiques', 'community-auctions' ),
				'slug' => 'art-antiques',
				'desc' => __( 'Artwork and antique items', 'community-auctions' ),
			),
			array(
				'name' => __( 'Jewelry', 'community-auctions' ),
				'slug' => 'jewelry',
				'desc' => __( 'Watches, rings, necklaces, and precious items', 'community-auctions' ),
			),
		);

		$term_ids = array();
		foreach ( $categories as $cat ) {
			$existing = term_exists( $cat['slug'], 'auction_category' );
			if ( $existing ) {
				$term_ids[ $cat['slug'] ] = $existing['term_id'];
			} else {
				$result = wp_insert_term(
					$cat['name'],
					'auction_category',
					array(
						'slug'        => $cat['slug'],
						'description' => $cat['desc'],
					)
				);
				if ( ! is_wp_error( $result ) ) {
					$term_ids[ $cat['slug'] ] = $result['term_id'];
					update_term_meta( $result['term_id'], '_ca_demo', true );
				}
			}
		}

		return $term_ids;
	}

	/**
	 * Create pages with shortcodes.
	 *
	 * @return array Created page IDs.
	 */
	private static function create_pages() {
		$pages = array(
			'submit-auction'    => array(
				'title'     => __( 'Submit Auction', 'community-auctions' ),
				'shortcode' => '[community_auction_submit]',
			),
			'my-auctions'       => array(
				'title'     => __( 'My Auctions', 'community-auctions' ),
				'shortcode' => '[community_auction_seller_dashboard]',
			),
			'my-purchases'      => array(
				'title'     => __( 'My Purchases', 'community-auctions' ),
				'shortcode' => '[community_auction_buyer_dashboard]',
			),
			'my-watchlist'      => array(
				'title'     => __( 'My Watchlist', 'community-auctions' ),
				'shortcode' => '[community_auction_watchlist]',
			),
			'search-auctions'   => array(
				'title'     => __( 'Search Auctions', 'community-auctions' ),
				'shortcode' => '[community_auctions_search]',
			),
			'upcoming-auctions' => array(
				'title'     => __( 'Upcoming Auctions', 'community-auctions' ),
				'shortcode' => '[community_auctions_upcoming]',
			),
		);

		$page_ids = array();
		foreach ( $pages as $slug => $page ) {
			// Check if page exists.
			$existing = get_page_by_path( $slug );
			if ( $existing ) {
				$page_ids[ $slug ] = $existing->ID;
				continue;
			}

			// Create page.
			$page_id = wp_insert_post(
				array(
					'post_title'   => $page['title'],
					'post_name'    => $slug,
					'post_content' => $page['shortcode'],
					'post_status'  => 'publish',
					'post_type'    => 'page',
				)
			);

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$page_ids[ $slug ] = $page_id;
				update_post_meta( $page_id, '_ca_demo', true );
			}
		}

		return $page_ids;
	}

	/**
	 * Create sample auctions.
	 *
	 * @param array $categories Category term IDs.
	 * @return array Created auction IDs.
	 */
	private static function create_auctions( $categories ) {
		$current_user_id = get_current_user_id();
		$other_sellers   = self::get_other_sellers( $current_user_id );

		$auctions = array(
			// Live auction with bids - by other seller.
			array(
				'title'       => __( 'Vintage Rolex Submariner Watch', 'community-auctions' ),
				'description' => __( 'Authentic 1985 Rolex Submariner in excellent condition. Complete with original box and papers. This timepiece features the classic black dial and rotating bezel. Perfect for collectors and watch enthusiasts.', 'community-auctions' ),
				'status'      => 'ca_live',
				'start_price' => 5000,
				'current_bid' => 6500,
				'category'    => 'jewelry',
				'end_days'    => 3,
				'seller_id'   => isset( $other_sellers[0] ) ? $other_sellers[0] : $current_user_id,
				'image_url'   => 'https://images.unsplash.com/photo-1523170335258-f5ed11844a49?w=800&q=80',
				'image_name'  => 'vintage-rolex-watch.jpg',
			),
			// Live auction no bids - current user is seller.
			array(
				'title'       => __( 'Sony PlayStation 5 Digital Edition', 'community-auctions' ),
				'description' => __( 'Brand new, sealed PlayStation 5 Digital Edition console. Includes DualSense controller. Perfect condition, never opened. Fast shipping available.', 'community-auctions' ),
				'status'      => 'ca_live',
				'start_price' => 400,
				'current_bid' => null,
				'category'    => 'electronics',
				'end_days'    => 5,
				'seller_id'   => $current_user_id,
				'image_url'   => 'https://images.unsplash.com/photo-1606144042614-b2417e99c4e3?w=800&q=80',
				'image_name'  => 'playstation-5-console.jpg',
			),
			// ENDING SOON auction - tests urgency countdown.
			array(
				'title'       => __( 'Limited Edition Sneakers - Nike Air Jordan', 'community-auctions' ),
				'description' => __( 'Brand new Nike Air Jordan 1 Retro High OG. Size 10 US. Limited edition colorway, never worn. Includes original box and authentication certificate. Hurry - auction ending soon!', 'community-auctions' ),
				'status'      => 'ca_live',
				'start_price' => 200,
				'current_bid' => 450,
				'category'    => 'collectibles',
				'end_minutes' => 45, // Ends in 45 minutes - urgency testing.
				'seller_id'   => isset( $other_sellers[1] ) ? $other_sellers[1] : $current_user_id,
				'image_url'   => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=800&q=80',
				'image_name'  => 'nike-sneakers.jpg',
			),
			// BUY NOW auction - tests instant purchase.
			array(
				'title'       => __( 'Apple MacBook Pro M3 - Brand New', 'community-auctions' ),
				'description' => __( 'Brand new Apple MacBook Pro with M3 chip, 16GB RAM, 512GB SSD. Factory sealed with Apple warranty. Buy it now for instant purchase or place a bid to try and get it cheaper!', 'community-auctions' ),
				'status'      => 'ca_live',
				'start_price' => 1500,
				'current_bid' => 1650,
				'buy_now'     => 2200, // Buy it now price.
				'category'    => 'electronics',
				'end_days'    => 4,
				'seller_id'   => isset( $other_sellers[0] ) ? $other_sellers[0] : $current_user_id,
				'image_url'   => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=800&q=80',
				'image_name'  => 'macbook-pro.jpg',
			),
			// Upcoming auction - starts in 2 days.
			array(
				'title'       => __( 'Original Oil Painting - Sunset Coast', 'community-auctions' ),
				'description' => __( 'Beautiful original oil painting by local artist. Size: 24x36 inches. Features a stunning coastal sunset scene with vibrant colors. Certificate of authenticity included.', 'community-auctions' ),
				'status'      => 'ca_pending',
				'start_price' => 800,
				'current_bid' => null,
				'category'    => 'art-antiques',
				'start_days'  => 2,
				'end_days'    => 9,
				'seller_id'   => $current_user_id,
				'image_url'   => 'https://images.unsplash.com/photo-1579783902614-a3fb3927b6a5?w=800&q=80',
				'image_name'  => 'sunset-oil-painting.jpg',
			),
			// Ended auction with winner - current user won (for buyer dashboard testing).
			array(
				'title'       => __( 'Rare Comic Book Collection', 'community-auctions' ),
				'description' => __( 'Collection of 25 vintage comic books from the 1960s-1970s. Includes first appearances and key issues. All graded and slabbed by CGC.', 'community-auctions' ),
				'status'      => 'ca_ended',
				'start_price' => 1500,
				'current_bid' => 2800,
				'category'    => 'collectibles',
				'end_days'    => -2,
				'has_winner'  => true,
				'winner_id'   => $current_user_id, // Current user is the winner.
				'seller_id'   => isset( $other_sellers[0] ) ? $other_sellers[0] : $current_user_id,
				'image_url'   => 'https://images.unsplash.com/photo-1612036782180-6f0b6cd846fe?w=800&q=80',
				'image_name'  => 'comic-book-collection.jpg',
			),
			// Ended auction with winner - current user is seller (for seller dashboard testing).
			array(
				'title'       => __( 'Diamond Engagement Ring - 1.5 Carat', 'community-auctions' ),
				'description' => __( 'Stunning 1.5 carat diamond engagement ring in 18k white gold setting. VS1 clarity, G color. Includes GIA certification and original case. A perfect symbol of love.', 'community-auctions' ),
				'status'      => 'ca_ended',
				'start_price' => 3000,
				'current_bid' => 4500,
				'category'    => 'jewelry',
				'end_days'    => -1,
				'has_winner'  => true,
				'seller_id'   => $current_user_id, // Current user is seller.
				'image_url'   => 'https://images.unsplash.com/photo-1515377905703-c4788e51af15?w=800&q=80',
				'image_name'  => 'diamond-ring.jpg',
			),
			// Ended auction no bids.
			array(
				'title'       => __( 'Antique Brass Telescope', 'community-auctions' ),
				'description' => __( 'Victorian-era brass telescope with wooden tripod. Fully functional with clear optics. Some patina consistent with age. A beautiful display piece.', 'community-auctions' ),
				'status'      => 'ca_ended',
				'start_price' => 350,
				'current_bid' => null,
				'category'    => 'art-antiques',
				'end_days'    => -5,
				'has_winner'  => false,
				'seller_id'   => isset( $other_sellers[1] ) ? $other_sellers[1] : $current_user_id,
				'image_url'   => 'https://images.unsplash.com/photo-1465101162946-4377e57745c3?w=800&q=80',
				'image_name'  => 'antique-telescope.jpg',
			),
		);

		$auction_ids = array();
		$now         = current_time( 'timestamp' );

		foreach ( $auctions as $auction ) {
			// Calculate dates.
			$start_days = isset( $auction['start_days'] ) ? $auction['start_days'] : 0;
			$end_days   = isset( $auction['end_days'] ) ? $auction['end_days'] : 7;

			$start_at = gmdate( 'Y-m-d H:i:s', $now + ( $start_days * DAY_IN_SECONDS ) );

			// Handle "end_minutes" for urgency testing (ending soon auctions).
			if ( isset( $auction['end_minutes'] ) ) {
				$end_at = gmdate( 'Y-m-d H:i:s', $now + ( $auction['end_minutes'] * MINUTE_IN_SECONDS ) );
			} else {
				$end_at = gmdate( 'Y-m-d H:i:s', $now + ( $end_days * DAY_IN_SECONDS ) );
			}

			// Determine seller ID.
			$seller_id = isset( $auction['seller_id'] ) ? $auction['seller_id'] : $current_user_id;

			// Create post.
			$post_id = wp_insert_post(
				array(
					'post_title'   => $auction['title'],
					'post_content' => $auction['description'],
					'post_status'  => $auction['status'],
					'post_type'    => 'auction',
					'post_author'  => $seller_id,
				)
			);

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				// Set meta.
				update_post_meta( $post_id, 'ca_start_price', $auction['start_price'] );
				update_post_meta( $post_id, 'ca_start_at', $start_at );
				update_post_meta( $post_id, 'ca_end_at', $end_at );
				update_post_meta( $post_id, 'ca_bid_increment', 50 );
				update_post_meta( $post_id, '_ca_demo', true );

				if ( $auction['current_bid'] ) {
					update_post_meta( $post_id, 'ca_current_bid', $auction['current_bid'] );
				}

				// Set Buy Now price if specified.
				if ( ! empty( $auction['buy_now'] ) ) {
					update_post_meta( $post_id, 'ca_buy_now_price', $auction['buy_now'] );
					update_post_meta( $post_id, 'ca_buy_now_enabled', true );
				}

				// Set winner for ended auctions.
				if ( ! empty( $auction['has_winner'] ) && $auction['current_bid'] ) {
					// Use specified winner_id or get a random user.
					$winner = isset( $auction['winner_id'] ) ? $auction['winner_id'] : self::get_random_user( $seller_id );
					if ( $winner ) {
						update_post_meta( $post_id, 'ca_winner_id', $winner );
						update_post_meta( $post_id, 'ca_highest_bidder', $winner );
					}
				}

				// Set category.
				if ( ! empty( $auction['category'] ) && isset( $categories[ $auction['category'] ] ) ) {
					wp_set_object_terms( $post_id, array( intval( $categories[ $auction['category'] ] ) ), 'auction_category' );
				}

				// Set featured image.
				if ( ! empty( $auction['image_url'] ) ) {
					$attachment_id = self::sideload_image( $auction['image_url'], $auction['image_name'], $post_id );
					if ( $attachment_id ) {
						set_post_thumbnail( $post_id, $attachment_id );
						update_post_meta( $attachment_id, '_ca_demo', true );
					}
				}

				$auction_ids[] = array(
					'id'          => $post_id,
					'status'      => $auction['status'],
					'current_bid' => $auction['current_bid'],
					'start_price' => $auction['start_price'],
					'has_winner'  => ! empty( $auction['has_winner'] ),
					'winner_id'   => isset( $auction['winner_id'] ) ? $auction['winner_id'] : null,
					'seller_id'   => $seller_id,
				);
			}
		}

		return $auction_ids;
	}

	/**
	 * Create sample bids on auctions (live and ended with bids).
	 *
	 * @param array $auctions Auction data.
	 */
	private static function create_bids( $auctions ) {
		foreach ( $auctions as $auction ) {
			// Skip auctions without bids.
			if ( ! $auction['current_bid'] ) {
				continue;
			}

			// Only process live and ended auctions.
			if ( 'ca_live' !== $auction['status'] && 'ca_ended' !== $auction['status'] ) {
				continue;
			}

			$auction_id   = $auction['id'];
			$current_bid  = $auction['current_bid'];
			$start_price  = $auction['start_price'];
			$seller_id    = isset( $auction['seller_id'] ) ? $auction['seller_id'] : get_current_user_id();
			$winner_id    = isset( $auction['winner_id'] ) ? $auction['winner_id'] : null;

			// Calculate intermediate bids.
			$bid_amounts = array(
				$start_price,
				$start_price + 200,
				$start_price + 500,
				$start_price + 1000,
				$current_bid,
			);

			// Filter to unique ascending values up to current bid.
			$bid_amounts = array_filter( $bid_amounts, function ( $amt ) use ( $current_bid, $start_price ) {
				return $amt >= $start_price && $amt <= $current_bid;
			} );
			$bid_amounts = array_unique( $bid_amounts );
			sort( $bid_amounts );

			// Get available bidders (excluding seller).
			$bidders = self::get_bidders_for_auction( $seller_id, $winner_id );

			// Insert bids.
			$bidder_index = 0;
			$total_bidders = count( $bidders );

			foreach ( $bid_amounts as $index => $amount ) {
				// For the last (highest) bid, use the winner if specified.
				if ( $index === count( $bid_amounts ) - 1 && $winner_id ) {
					$bidder_id = $winner_id;
				} else {
					// Alternate between bidders for realistic bid history.
					$bidder_id = $total_bidders > 0 ? $bidders[ $bidder_index % $total_bidders ] : null;
					++$bidder_index;
				}

				if ( ! $bidder_id ) {
					continue;
				}

				Community_Auctions_Bid_Repository::insert_bid(
					$auction_id,
					$bidder_id,
					$amount
				);
			}

			// Update bid count.
			$bid_count = Community_Auctions_Bid_Repository::count_auction_bids( $auction_id );
			update_post_meta( $auction_id, 'ca_bid_count', $bid_count );

			// Update unique bidders count.
			$unique_bidders = Community_Auctions_Bid_Repository::count_unique_bidders( $auction_id );
			update_post_meta( $auction_id, 'ca_unique_bidders', $unique_bidders );

			// Set highest bidder.
			$highest = Community_Auctions_Bid_Repository::get_highest_bid( $auction_id );
			if ( $highest ) {
				update_post_meta( $auction_id, 'ca_highest_bidder', $highest->user_id );
			}
		}
	}

	/**
	 * Add auction pages to the primary navigation menu.
	 *
	 * Adds pages to the existing primary menu instead of creating a separate menu.
	 *
	 * @param array $pages Page IDs.
	 */
	private static function add_pages_to_menu( $pages ) {
		// Find the primary menu.
		$locations    = get_theme_mod( 'nav_menu_locations', array() );
		$location_key = null;

		// Check common menu location names (including BuddyX theme locations).
		$possible_locations = array( 'primary', 'menu-1', 'main-menu', 'primary-menu', 'main', 'header-menu' );
		foreach ( $possible_locations as $loc ) {
			if ( ! empty( $locations[ $loc ] ) ) {
				$location_key = $loc;
				break;
			}
		}

		// If no primary menu found, get the first available menu or create one.
		if ( ! $location_key ) {
			$menus = wp_get_nav_menus();
			if ( ! empty( $menus ) ) {
				$menu_id = $menus[0]->term_id;
			} else {
				// Create a new menu only if none exist.
				$menu_id = wp_create_nav_menu( __( 'Primary Menu', 'community-auctions' ) );
				if ( is_wp_error( $menu_id ) ) {
					return;
				}
				// Assign to primary location.
				$locations['primary'] = $menu_id;
				set_theme_mod( 'nav_menu_locations', $locations );
			}
		} else {
			$menu_id = $locations[ $location_key ];
		}

		// Get current highest menu order.
		$menu_items = wp_get_nav_menu_items( $menu_id );
		$max_order  = 0;
		if ( $menu_items ) {
			foreach ( $menu_items as $item ) {
				if ( $item->menu_order > $max_order ) {
					$max_order = $item->menu_order;
				}
			}
		}

		// Track added item IDs for cleanup.
		$added_items = array();

		// Add "Auctions" parent menu item with link to archive.
		$parent_item_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'    => __( 'Auctions', 'community-auctions' ),
				'menu-item-url'      => get_post_type_archive_link( 'auction' ),
				'menu-item-status'   => 'publish',
				'menu-item-type'     => 'custom',
				'menu-item-position' => $max_order + 1,
			)
		);

		if ( ! is_wp_error( $parent_item_id ) ) {
			$added_items[] = $parent_item_id;
			update_post_meta( $parent_item_id, '_ca_demo', true );

			// Add pages as sub-items under "Auctions".
			// Child positions must come after parent position for proper menu ordering.
			$sub_order = $max_order + 2;
			foreach ( $pages as $slug => $page_id ) {
				$item_id = wp_update_nav_menu_item(
					$menu_id,
					0,
					array(
						'menu-item-object-id'   => $page_id,
						'menu-item-object'      => 'page',
						'menu-item-parent-id'   => $parent_item_id,
						'menu-item-type'        => 'post_type',
						'menu-item-status'      => 'publish',
						'menu-item-position'    => $sub_order,
					)
				);

				if ( ! is_wp_error( $item_id ) ) {
					$added_items[] = $item_id;
					update_post_meta( $item_id, '_ca_demo', true );
				}
				++$sub_order;
			}
		}

		// Store menu ID and added items for cleanup.
		update_option( 'ca_demo_menu_id', $menu_id );
		update_option( 'ca_demo_menu_items', $added_items );
	}

	/**
	 * Create BuddyPress activities for live auctions.
	 *
	 * Creates activity stream entries for auctions that are live.
	 *
	 * @param array $auctions Auction data array.
	 */
	private static function create_buddypress_activities( $auctions ) {
		// Check if BuddyPress is active.
		if ( ! function_exists( 'bp_activity_add' ) ) {
			return;
		}

		foreach ( $auctions as $auction ) {
			// Only create activities for live auctions.
			if ( 'ca_live' !== $auction['status'] ) {
				continue;
			}

			$auction_id   = $auction['id'];
			$auction_post = get_post( $auction_id );

			if ( ! $auction_post ) {
				continue;
			}

			$user_id       = $auction_post->post_author;
			$auction_title = $auction_post->post_title;
			$auction_url   = get_permalink( $auction_id );
			$start_price   = isset( $auction['start_price'] ) ? $auction['start_price'] : 0;

			// Get user display name.
			$user_link = bp_core_get_userlink( $user_id );

			// Build activity action text.
			$action = sprintf(
				/* translators: 1: user link, 2: auction link */
				__( '%1$s started a new auction: %2$s', 'community-auctions' ),
				$user_link,
				sprintf( '<a href="%s">%s</a>', esc_url( $auction_url ), esc_html( $auction_title ) )
			);

			// Build activity content.
			$content = '';
			if ( $start_price > 0 ) {
				$formatted_price = Community_Auctions_Currency::format( $start_price );
				$content         = sprintf(
					/* translators: %s: starting price */
					__( 'Starting bid: %s', 'community-auctions' ),
					$formatted_price
				);
			}

			// Create the activity.
			$activity_id = bp_activity_add(
				array(
					'user_id'           => $user_id,
					'action'            => $action,
					'content'           => $content,
					'component'         => 'community_auctions',
					'type'              => 'auction_created',
					'primary_link'      => $auction_url,
					'item_id'           => $auction_id,
					'secondary_item_id' => 0,
					'hide_sitewide'     => false,
				)
			);

			// Mark as demo data for cleanup.
			if ( $activity_id ) {
				bp_activity_update_meta( $activity_id, '_ca_demo', true );
			}
		}
	}

	/**
	 * Create WooCommerce orders for won auctions.
	 *
	 * @param array $auctions Auction data.
	 */
	private static function create_woocommerce_orders( $auctions ) {
		// Check if WooCommerce is available.
		if ( ! Community_Auctions_Payment_WooCommerce::is_available() ) {
			return;
		}

		foreach ( $auctions as $auction ) {
			// Only process ended auctions with winners.
			if ( 'ca_ended' !== $auction['status'] || ! $auction['has_winner'] ) {
				continue;
			}

			$auction_id = $auction['id'];
			$winner_id  = get_post_meta( $auction_id, 'ca_winner_id', true );
			$amount     = $auction['current_bid'];

			if ( ! $winner_id || ! $amount ) {
				continue;
			}

			// Create WooCommerce order.
			$order = Community_Auctions_Payment_WooCommerce::create_order_for_auction(
				$auction_id,
				$winner_id,
				$amount,
				0 // No fee for demo.
			);

			if ( ! is_wp_error( $order ) && $order ) {
				// Store order ID on auction.
				update_post_meta( $auction_id, 'ca_order_id', $order->get_id() );
				update_post_meta( $auction_id, 'ca_payment_provider', 'woocommerce' );

				// Mark order as demo data.
				$order->update_meta_data( '_ca_demo', true );
				$order->save();
			}
		}
	}

	/**
	 * Create watchlist entries for testing.
	 *
	 * @param array $auctions Auction data.
	 */
	private static function create_watchlist_entries( $auctions ) {
		$current_user_id = get_current_user_id();

		if ( ! $current_user_id ) {
			return;
		}

		// Add some live and upcoming auctions to current user's watchlist.
		foreach ( $auctions as $auction ) {
			// Only watch live and pending (upcoming) auctions not created by current user.
			if ( ! in_array( $auction['status'], array( 'ca_live', 'ca_pending' ), true ) ) {
				continue;
			}

			// Skip if current user is the seller.
			if ( isset( $auction['seller_id'] ) && $auction['seller_id'] === $current_user_id ) {
				continue;
			}

			// Add to watchlist.
			Community_Auctions_Watchlist::add_to_watchlist( $current_user_id, $auction['id'] );
		}
	}

	/**
	 * Get other users to act as sellers (excluding current user).
	 *
	 * @param int $exclude_user_id User ID to exclude.
	 * @return array Array of user IDs.
	 */
	private static function get_other_sellers( $exclude_user_id ) {
		$users = get_users(
			array(
				'fields'  => 'ID',
				'number'  => 5,
				'exclude' => array( $exclude_user_id ),
			)
		);

		return $users;
	}

	/**
	 * Get bidders for an auction (excluding seller and optionally prioritizing winner).
	 *
	 * @param int      $seller_id Seller user ID to exclude.
	 * @param int|null $winner_id Winner user ID (will be included in the list).
	 * @return array Array of user IDs.
	 */
	private static function get_bidders_for_auction( $seller_id, $winner_id = null ) {
		$exclude = array( $seller_id );

		$users = get_users(
			array(
				'fields'  => 'ID',
				'number'  => 10,
				'exclude' => $exclude,
			)
		);

		// Ensure winner is in the list if specified.
		if ( $winner_id && ! in_array( $winner_id, $users, true ) ) {
			$users[] = $winner_id;
		}

		return $users;
	}

	/**
	 * Get a random user ID for bids.
	 *
	 * @param int $exclude_user_id Optional user ID to exclude (e.g., seller).
	 * @return int|false User ID or false.
	 */
	private static function get_random_user( $exclude_user_id = 0 ) {
		static $users = null;

		if ( null === $users ) {
			$users = get_users(
				array(
					'fields' => 'ID',
					'number' => 20,
				)
			);
		}

		if ( empty( $users ) ) {
			return false;
		}

		// Filter out excluded user.
		$available_users = $users;
		if ( $exclude_user_id ) {
			$available_users = array_filter( $users, function ( $uid ) use ( $exclude_user_id ) {
				return absint( $uid ) !== absint( $exclude_user_id );
			} );
			$available_users = array_values( $available_users );
		}

		if ( empty( $available_users ) ) {
			return false;
		}

		return $available_users[ array_rand( $available_users ) ];
	}

	/**
	 * Sideload an image from URL and attach to post.
	 *
	 * Downloads an image from external URL and adds it to the media library.
	 *
	 * @param string $url       The URL of the image to download.
	 * @param string $filename  Desired filename for the image.
	 * @param int    $post_id   The post ID to attach the image to.
	 * @return int|false Attachment ID on success, false on failure.
	 */
	private static function sideload_image( $url, $filename, $post_id ) {
		// Require media files for sideloading.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Download file to temp location.
		$temp_file = download_url( $url );

		if ( is_wp_error( $temp_file ) ) {
			return false;
		}

		// Prepare file array for sideloading.
		$file_array = array(
			'name'     => sanitize_file_name( $filename ),
			'tmp_name' => $temp_file,
		);

		// Sideload the image into the media library.
		$attachment_id = media_handle_sideload( $file_array, $post_id );

		// Clean up temp file if sideload failed.
		if ( is_wp_error( $attachment_id ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $temp_file );
			return false;
		}

		return $attachment_id;
	}

	/**
	 * Remove all demo data.
	 */
	public static function remove_demo_data() {
		global $wpdb;

		// Remove demo auctions and their bids (batch processing).
		$demo_auctions = get_posts(
			array(
				'post_type'      => 'auction',
				'posts_per_page' => 100, // Bounded query - admin cleanup in batches.
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_ca_demo',
						'value' => '1',
					),
				),
			)
		);

		foreach ( $demo_auctions as $auction_id ) {
			// Delete bids.
			$table = $wpdb->prefix . 'ca_bids';
			$wpdb->delete( $table, array( 'auction_id' => $auction_id ) );

			// Delete watchlist entries for this auction.
			$watchlist_table = $wpdb->prefix . 'ca_watchlist';
			$wpdb->delete( $watchlist_table, array( 'auction_id' => $auction_id ) );

			// Delete auction.
			wp_delete_post( $auction_id, true );
		}

		// Remove demo WooCommerce orders.
		if ( class_exists( 'WooCommerce' ) ) {
			$demo_orders = wc_get_orders(
				array(
					'limit'      => 100,
					'meta_key'   => '_ca_demo',
					'meta_value' => '1',
				)
			);

			foreach ( $demo_orders as $order ) {
				$order->delete( true );
			}
		}

		// Remove demo pages.
		$demo_pages = get_posts(
			array(
				'post_type'      => 'page',
				'posts_per_page' => 100, // Bounded query - admin cleanup.
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_ca_demo',
						'value' => '1',
					),
				),
			)
		);

		foreach ( $demo_pages as $page_id ) {
			wp_delete_post( $page_id, true );
		}

		// Remove demo media attachments.
		$demo_media = get_posts(
			array(
				'post_type'      => 'attachment',
				'posts_per_page' => 100, // Bounded query - admin cleanup.
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_ca_demo',
						'value' => '1',
					),
				),
			)
		);

		foreach ( $demo_media as $attachment_id ) {
			wp_delete_attachment( $attachment_id, true );
		}

		// Remove demo categories.
		$demo_terms = get_terms(
			array(
				'taxonomy'   => 'auction_category',
				'hide_empty' => false,
				'meta_query' => array(
					array(
						'key'   => '_ca_demo',
						'value' => '1',
					),
				),
			)
		);

		if ( ! is_wp_error( $demo_terms ) ) {
			foreach ( $demo_terms as $term ) {
				wp_delete_term( $term->term_id, 'auction_category' );
			}
		}

		// Remove demo menu items (not the entire menu).
		$menu_items = get_option( 'ca_demo_menu_items', array() );
		if ( ! empty( $menu_items ) ) {
			foreach ( $menu_items as $item_id ) {
				wp_delete_post( $item_id, true );
			}
			delete_option( 'ca_demo_menu_items' );
		}
		delete_option( 'ca_demo_menu_id' );

		// Remove demo BuddyPress activities.
		if ( function_exists( 'bp_activity_delete' ) ) {
			global $wpdb;
			$bp_prefix = bp_core_get_table_prefix();

			// Get activity IDs with demo meta.
			$activity_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT activity_id FROM {$bp_prefix}bp_activity_meta WHERE meta_key = %s AND meta_value = %s",
					'_ca_demo',
					'1'
				)
			);

			if ( ! empty( $activity_ids ) ) {
				foreach ( $activity_ids as $activity_id ) {
					bp_activity_delete( array( 'id' => $activity_id ) );
				}
			}
		}
	}

	/**
	 * Check if demo data is imported.
	 *
	 * @return bool True if imported.
	 */
	public static function is_imported() {
		return (bool) get_option( self::IMPORTED_OPTION, false );
	}
}
