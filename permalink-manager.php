* @var string
	 */
	private $option_key;

	/**
	 * Reserved slugs to avoid conflicts.
	 *
	 * @var array
	 */
	private $reserved_slugs = array(
		'wp-admin',
		'wp-login',
		'wp-content',
		'wp-includes',
		'feed',
		'page',
		'attachment',
		'category',
		'tag',
		'author',
		'search'
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->option_key = 'permalink_manager_links';
		add_action( 'init', array( $this, 'register_custom_permalinks' ) );
		register_activation_hook( __FILE__, array( $this, 'flush_rewrite_rules' ) );
		register_deactivation_hook( __FILE__, array( $this, 'flush_rewrite_rules' ) );
	}

	/**
	 * Generate unique permalink for a given plugin slug.
	 *
	 * @param string $plugin_slug
	 * @return string
	 */
	public function generate_permalink( $plugin_slug ) {
		$slug = $this->generate_unique_slug( sanitize_title( $plugin_slug ) );
		$base = home_url( '/' );
		$permalink = trailingslashit( $base . $slug );
		$this->update_permalink( $plugin_slug, $permalink );
		return $permalink;
	}

	/**
	 * Ensure slug is unique and not reserved.
	 *
	 * @param string $slug
	 * @return string
	 */
	private function generate_unique_slug( $slug ) {
		$permalinks = get_option( $this->option_key, array() );

		// Ensure reserved and existing slugs are avoided
		$all_slugs_in_use = array_merge( array_keys( $permalinks ), $this->reserved_slugs );
		$unique_slug = $slug;
		$counter     = 1;
		while ( in_array( $unique_slug, $all_slugs_in_use, true ) ) {
			$unique_slug = $slug . '-' . $counter;
			$counter++;
		}
		return $unique_slug;
	}

	/**
	 * Update a permalink in options table.
	 *
	 * @param string $plugin_slug
	 * @param string $permalink
	 */
	public function update_permalink( $plugin_slug, $permalink ) {
		$permalinks = get_option( $this->option_key, array() );
		$permalinks[ sanitize_key( $plugin_slug ) ] = esc_url_raw( $permalink );
		update_option( $this->option_key, $permalinks, false );
	}

	/**
	 * Get stored permalink for a plugin slug.
	 *
	 * @param string $plugin_slug
	 * @return string
	 */
	public function get_permalink( $plugin_slug ) {
		$permalinks = get_option( $this->option_key, array() );
		$slug       = sanitize_key( $plugin_slug );
		return isset( $permalinks[ $slug ] ) ? esc_url( $permalinks[ $slug ] ) : '';
	}

	/**
	 * Register rewrite rules for stored permalinks.
	 */
	public function register_custom_permalinks() {
		$permalinks = get_option( $this->option_key, array() );
		foreach ( $permalinks as $slug => $url ) {
			add_rewrite_rule(
				$slug . '/?$',
				'index.php?custom_plugin_page=' . $slug,
				'top'
			);
		}
		add_rewrite_tag( '%custom_plugin_page%', '([^&]+)' );
	}

	/**
	 * Flush rewrite rules once during activation/deactivation or when slugs change.
	 */
	public function flush_rewrite_rules() {
		flush_rewrite_rules();
	}

}

new Permalink_Manager();