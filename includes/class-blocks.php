<?php
/**
 * Gutenberg Blocks Registration
 *
 * @package CommunityAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Community Auctions Blocks Handler.
 */
class Community_Auctions_Blocks {

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	private static $plugin_dir;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	private static $plugin_url;

	/**
	 * Initialize blocks.
	 *
	 * @param string $plugin_dir Plugin directory path.
	 * @param string $plugin_url Plugin URL.
	 */
	public static function init( $plugin_dir, $plugin_url ) {
		self::$plugin_dir = $plugin_dir;
		self::$plugin_url = $plugin_url;

		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_filter( 'block_categories_all', array( __CLASS__, 'add_block_category' ), 10, 2 );
	}

	/**
	 * Register all Gutenberg blocks.
	 */
	public static function register_blocks() {
		// Check if build files exist.
		$build_dir = self::$plugin_dir . 'build/';

		if ( ! file_exists( $build_dir . 'index.asset.php' ) ) {
			// Build files don't exist yet - blocks will be registered after npm build.
			return;
		}

		// Register blocks using block.json.
		$blocks = array(
			'auction-grid',
			'single-auction',
			'countdown-timer',
		);

		foreach ( $blocks as $block ) {
			$block_dir = self::$plugin_dir . 'src/blocks/' . $block;

			if ( file_exists( $block_dir . '/block.json' ) ) {
				register_block_type( $block_dir );
			}
		}

		// Enqueue frontend styles.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_styles' ) );
	}

	/**
	 * Enqueue frontend styles for blocks.
	 */
	public static function enqueue_frontend_styles() {
		$build_dir = self::$plugin_dir . 'build/';
		$build_url = self::$plugin_url . 'build/';

		if ( ! file_exists( $build_dir . 'index.asset.php' ) ) {
			return;
		}

		$asset_file = include $build_dir . 'index.asset.php';

		// Frontend styles (built from all block style.css files).
		if ( file_exists( $build_dir . 'style-index.css' ) ) {
			wp_enqueue_style(
				'community-auctions-blocks',
				$build_url . 'style-index.css',
				array(),
				$asset_file['version']
			);
		}
	}

	/**
	 * Enqueue editor assets.
	 */
	public static function enqueue_editor_assets() {
		$build_dir = self::$plugin_dir . 'build/';
		$build_url = self::$plugin_url . 'build/';

		if ( ! file_exists( $build_dir . 'index.asset.php' ) ) {
			return;
		}

		$asset_file = include $build_dir . 'index.asset.php';

		// Editor script.
		wp_enqueue_script(
			'community-auctions-blocks-editor',
			$build_url . 'index.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		// Localize script with REST URL and nonce.
		wp_localize_script(
			'community-auctions-blocks-editor',
			'communityAuctionsBlocks',
			array(
				'restUrl'  => rest_url( 'wp/v2/' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'settings' => Community_Auctions_Settings::get_settings(),
			)
		);

		// Editor styles.
		if ( file_exists( $build_dir . 'index.css' ) ) {
			wp_enqueue_style(
				'community-auctions-blocks-editor',
				$build_url . 'index.css',
				array(),
				$asset_file['version']
			);
		}
	}

	/**
	 * Add custom block category for Community Auctions.
	 *
	 * @param array                   $categories Block categories.
	 * @param WP_Block_Editor_Context $context    Block editor context.
	 * @return array Modified categories.
	 */
	public static function add_block_category( $categories, $context ) {
		$settings = Community_Auctions_Settings::get_settings();
		$label    = $settings['label_plural'] ?? __( 'Auctions', 'community-auctions' );

		return array_merge(
			array(
				array(
					'slug'  => 'community-auctions',
					'title' => $label,
					'icon'  => 'money-alt',
				),
			),
			$categories
		);
	}

	/**
	 * Check if blocks are ready (build files exist).
	 *
	 * @return bool True if blocks are built and ready.
	 */
	public static function blocks_ready() {
		return file_exists( self::$plugin_dir . 'build/index.asset.php' );
	}

	/**
	 * Get admin notice if blocks need building.
	 */
	public static function maybe_show_build_notice() {
		if ( ! self::blocks_ready() && current_user_can( 'manage_options' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'show_build_notice' ) );
		}
	}

	/**
	 * Show notice that blocks need building.
	 */
	public static function show_build_notice() {
		?>
		<div class="notice notice-warning">
			<p>
				<strong><?php esc_html_e( 'Community Auctions:', 'community-auctions' ); ?></strong>
				<?php
				printf(
					/* translators: %1$s: npm install command, %2$s: npm build command */
					esc_html__( 'Gutenberg blocks need to be built. Run %1$s and then %2$s in the plugin directory.', 'community-auctions' ),
					'<code>npm install</code>',
					'<code>npm run build</code>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
