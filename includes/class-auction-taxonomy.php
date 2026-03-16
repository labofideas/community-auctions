<?php
/**
 * Auction Taxonomy - Registers auction categories taxonomy.
 *
 * @package Community_Auctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Community_Auctions_Taxonomy
 *
 * Handles auction category taxonomy registration and management.
 */
class Community_Auctions_Taxonomy {

	/**
	 * Taxonomy slug.
	 */
	const TAXONOMY = 'auction_category';

	/**
	 * Register hooks.
	 */
	public static function register() {
		// If init has already fired, register directly. Otherwise, hook to init.
		if ( did_action( 'init' ) ) {
			self::register_taxonomy();
		} else {
			add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );
		}
		add_filter( 'manage_edit-auction_columns', array( __CLASS__, 'add_category_column' ) );
		add_action( 'manage_auction_posts_custom_column', array( __CLASS__, 'render_category_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_category_filter' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'handle_category_filter' ) );
	}

	/**
	 * Register the auction_category taxonomy.
	 */
	public static function register_taxonomy() {
		$labels = array(
			'name'                       => _x( 'Auction Categories', 'taxonomy general name', 'community-auctions' ),
			'singular_name'              => _x( 'Auction Category', 'taxonomy singular name', 'community-auctions' ),
			'search_items'               => __( 'Search Categories', 'community-auctions' ),
			'popular_items'              => __( 'Popular Categories', 'community-auctions' ),
			'all_items'                  => __( 'All Categories', 'community-auctions' ),
			'parent_item'                => __( 'Parent Category', 'community-auctions' ),
			'parent_item_colon'          => __( 'Parent Category:', 'community-auctions' ),
			'edit_item'                  => __( 'Edit Category', 'community-auctions' ),
			'view_item'                  => __( 'View Category', 'community-auctions' ),
			'update_item'                => __( 'Update Category', 'community-auctions' ),
			'add_new_item'               => __( 'Add New Category', 'community-auctions' ),
			'new_item_name'              => __( 'New Category Name', 'community-auctions' ),
			'separate_items_with_commas' => __( 'Separate categories with commas', 'community-auctions' ),
			'add_or_remove_items'        => __( 'Add or remove categories', 'community-auctions' ),
			'choose_from_most_used'      => __( 'Choose from the most used categories', 'community-auctions' ),
			'not_found'                  => __( 'No categories found.', 'community-auctions' ),
			'no_terms'                   => __( 'No categories', 'community-auctions' ),
			'items_list_navigation'      => __( 'Categories list navigation', 'community-auctions' ),
			'items_list'                 => __( 'Categories list', 'community-auctions' ),
			'back_to_items'              => __( '&larr; Back to Categories', 'community-auctions' ),
			'menu_name'                  => __( 'Categories', 'community-auctions' ),
		);

		$args = array(
			'hierarchical'          => true,
			'labels'                => $labels,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'show_in_nav_menus'     => true,
			'show_tagcloud'         => true,
			'query_var'             => true,
			'rewrite'               => array(
				'slug'         => 'auction-category',
				'with_front'   => false,
				'hierarchical' => true,
			),
			'show_in_rest'          => true,
			'rest_base'             => 'auction-categories',
			'rest_controller_class' => 'WP_REST_Terms_Controller',
		);

		register_taxonomy( self::TAXONOMY, 'auction', $args );
	}

	/**
	 * Add category column to auction list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public static function add_category_column( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			if ( 'title' === $key ) {
				$new_columns['auction_category'] = __( 'Category', 'community-auctions' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render category column content.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public static function render_category_column( $column, $post_id ) {
		if ( 'auction_category' !== $column ) {
			return;
		}

		$terms = get_the_terms( $post_id, self::TAXONOMY );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			echo '<span class="na">&mdash;</span>';
			return;
		}

		$term_links = array();
		foreach ( $terms as $term ) {
			$term_links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( add_query_arg( array( 'auction_category' => $term->slug ), admin_url( 'edit.php?post_type=auction' ) ) ),
				esc_html( $term->name )
			);
		}

		echo wp_kses_post( implode( ', ', $term_links ) );
	}

	/**
	 * Render category filter dropdown in admin.
	 */
	public static function render_category_filter() {
		global $typenow;

		if ( 'auction' !== $typenow ) {
			return;
		}

		$selected = isset( $_GET['auction_category'] ) ? sanitize_text_field( wp_unslash( $_GET['auction_category'] ) ) : '';

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		echo '<select name="auction_category" id="auction_category">';
		echo '<option value="">' . esc_html__( 'All Categories', 'community-auctions' ) . '</option>';

		foreach ( $terms as $term ) {
			printf(
				'<option value="%s" %s>%s (%d)</option>',
				esc_attr( $term->slug ),
				selected( $selected, $term->slug, false ),
				esc_html( $term->name ),
				esc_html( $term->count )
			);
		}

		echo '</select>';
	}

	/**
	 * Handle category filter in admin query.
	 *
	 * @param WP_Query $query The query object.
	 */
	public static function handle_category_filter( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'auction' !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( empty( $_GET['auction_category'] ) ) {
			return;
		}

		$category = sanitize_text_field( wp_unslash( $_GET['auction_category'] ) );

		$tax_query = $query->get( 'tax_query' );
		if ( ! is_array( $tax_query ) ) {
			$tax_query = array();
		}

		$tax_query[] = array(
			'taxonomy' => self::TAXONOMY,
			'field'    => 'slug',
			'terms'    => $category,
		);

		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Get all auction categories.
	 *
	 * @param array $args Optional. Arguments for get_terms.
	 * @return array Array of term objects.
	 */
	public static function get_categories( $args = array() ) {
		$defaults = array(
			'taxonomy'   => self::TAXONOMY,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		$args  = wp_parse_args( $args, $defaults );
		$terms = get_terms( $args );

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return $terms;
	}

	/**
	 * Get categories for an auction.
	 *
	 * @param int $auction_id Auction ID.
	 * @return array Array of term objects.
	 */
	public static function get_auction_categories( $auction_id ) {
		$terms = get_the_terms( $auction_id, self::TAXONOMY );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		return $terms;
	}

	/**
	 * Set categories for an auction.
	 *
	 * @param int   $auction_id   Auction ID.
	 * @param array $category_ids Array of category term IDs.
	 * @return array|WP_Error Array of term taxonomy IDs or WP_Error.
	 */
	public static function set_auction_categories( $auction_id, $category_ids ) {
		$category_ids = array_map( 'absint', (array) $category_ids );
		return wp_set_object_terms( $auction_id, $category_ids, self::TAXONOMY );
	}

	/**
	 * Render category selector for frontend forms.
	 *
	 * @param int   $auction_id  Optional. Auction ID for pre-selection.
	 * @param array $args        Optional. Additional arguments.
	 * @return string HTML output.
	 */
	public static function render_category_selector( $auction_id = 0, $args = array() ) {
		$defaults = array(
			'multiple'    => false,
			'required'    => false,
			'placeholder' => __( 'Select a category', 'community-auctions' ),
			'class'       => 'ca-category-select',
			'id'          => '',
			'show_label'  => true,
		);

		$args = wp_parse_args( $args, $defaults );

		$terms = self::get_categories();
		if ( empty( $terms ) ) {
			return '';
		}

		$selected_ids = array();
		if ( $auction_id ) {
			$selected_terms = self::get_auction_categories( $auction_id );
			$selected_ids   = wp_list_pluck( $selected_terms, 'term_id' );
		}

		$name = $args['multiple'] ? 'ca_categories[]' : 'ca_category';

		// Use custom ID if provided, otherwise generate one.
		if ( ! empty( $args['id'] ) ) {
			$id = $args['id'];
		} else {
			$id = $args['multiple'] ? 'ca-categories' : 'ca-category';
		}

		$multiple = $args['multiple'] ? ' multiple' : '';
		$required = $args['required'] ? ' required' : '';

		ob_start();

		if ( $args['show_label'] ) {
			?>
			<p>
				<label for="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Category', 'community-auctions' ); ?></label>
			<?php
		}
		?>
			<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $args['class'] ); ?>"<?php echo esc_attr( $multiple . $required ); ?>>
				<?php if ( ! $args['multiple'] ) : ?>
					<option value=""><?php echo esc_html( $args['placeholder'] ); ?></option>
				<?php endif; ?>
				<?php echo self::render_category_options( $terms, $selected_ids ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</select>
		<?php
		if ( $args['show_label'] ) {
			?>
			</p>
			<?php
		}

		return ob_get_clean();
	}

	/**
	 * Render hierarchical category options.
	 *
	 * @param array $terms        Terms to render.
	 * @param array $selected_ids Selected term IDs.
	 * @param int   $parent       Parent term ID.
	 * @param int   $depth        Current depth level.
	 * @return string HTML options.
	 */
	private static function render_category_options( $terms, $selected_ids, $parent = 0, $depth = 0 ) {
		$output = '';

		foreach ( $terms as $term ) {
			if ( $term->parent !== $parent ) {
				continue;
			}

			$selected = in_array( $term->term_id, $selected_ids, true ) ? ' selected' : '';
			$prefix   = str_repeat( '&mdash; ', $depth );

			$output .= sprintf(
				'<option value="%d"%s>%s%s</option>',
				esc_attr( $term->term_id ),
				$selected,
				$prefix,
				esc_html( $term->name )
			);

			$output .= self::render_category_options( $terms, $selected_ids, $term->term_id, $depth + 1 );
		}

		return $output;
	}

	/**
	 * Get category archive URL.
	 *
	 * @param int|WP_Term $term Term ID or object.
	 * @return string Category archive URL.
	 */
	public static function get_category_url( $term ) {
		if ( is_numeric( $term ) ) {
			$term = get_term( $term, self::TAXONOMY );
		}

		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}

		return get_term_link( $term, self::TAXONOMY );
	}
}
