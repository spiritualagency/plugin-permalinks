function permalink_publisher_activate() {
	if ( false === get_option( 'permalink_publisher_settings' ) ) {
		add_option(
			'permalink_publisher_settings',
			array(
				'enabled'   => true,
				'structure' => '/%postname%/',
			)
		);
	}
	flush_rewrite_rules();
}

/**
 * Plugin deactivation hook.
 */
function permalink_publisher_deactivate() {
	flush_rewrite_rules();
}

/**
 * Initialize the plugin: load textdomain and set up filters.
 */
function permalink_publisher_init() {
	load_plugin_textdomain(
		'permalink-publisher',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	if ( ! is_admin() ) {
		add_filter( 'post_link', 'permalink_publisher_generate_permalink', 10, 3 );
		add_filter( 'page_link', 'permalink_publisher_generate_permalink', 10, 2 );
	}
}

/**
 * Generate or retrieve a tokenized permalink for a post/page.
 *
 * @param string       $permalink Existing permalink.
 * @param int|WP_Post  $post      Post object or ID.
 * @param bool         $leavename Leave name for future processing.
 * @return string                 New permalink if enabled, or existing if not.
 */
function permalink_publisher_generate_permalink( $permalink, $post, $leavename = false ) {
	$settings = get_option( 'permalink_publisher_settings', array() );
	if ( empty( $settings['enabled'] ) ) {
		return $permalink;
	}

	// Normalize $post to WP_Post object.
	$post_obj = null;
	if ( is_numeric( $post ) ) {
		$post_obj = get_post( (int) $post );
	} elseif ( $post instanceof WP_Post ) {
		$post_obj = $post;
	} else {
		return $permalink;
	}

	if ( ! $post_obj || empty( $post_obj->ID ) ) {
		return $permalink;
	}

	// Only allow meta updating if user has permission and in admin or safe context.
	if ( empty( $token = get_post_meta( $post_obj->ID, '_permalink_publisher_token', true ) ) ) {
		if ( is_admin() && current_user_can( 'edit_post', $post_obj->ID ) ) {
			$token = permalink_publisher_generate_unique_token();
			update_post_meta( $post_obj->ID, '_permalink_publisher_token', $token );
		} elseif ( ! is_admin() ) {
			// On front end, do not write meta if missing.
			return $permalink;
		}
	}

	return $token ? home_url( trailingslashit( 'p/' . $token ) ) : $permalink;
}

/**
 * Generate a unique token that does not collide with existing post meta.
 *
 * @param int $length Length of token.
 * @return string
 */
function permalink_publisher_generate_unique_token( $length = 12 ) {
	global $wpdb;
	do {
		$token   = wp_generate_password( $length, false );
		$exists  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				'_permalink_publisher_token',
				$token
			)
		);
	} while ( ! empty( $exists ) );
	return $token;
}

register_activation_hook( __FILE__, 'permalink_publisher_activate' );
register_deactivation_hook( __FILE__, 'permalink_publisher_deactivate' );
add_action( 'plugins_loaded', 'permalink_publisher_init' );