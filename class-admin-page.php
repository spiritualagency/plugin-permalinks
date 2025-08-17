public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu_page' ) );
		add_action( 'admin_post_myplugin_save_settings', array( $this, 'save_settings' ) );
	}

	public function register_menu_page() {
		add_menu_page(
			__( 'My Plugin Settings', 'myplugin' ),
			__( 'My Plugin', 'myplugin' ),
			'manage_options',
			'myplugin-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-admin-generic',
			65
		);
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = get_option( $this->option_name, array() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'My Plugin Settings', 'myplugin' ); ?></h1>
			<?php if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) : ?>
				<div id="message" class="updated notice is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'myplugin' ); ?></p>
				</div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'myplugin_save_settings', 'myplugin_nonce' ); ?>
				<input type="hidden" name="action" value="myplugin_save_settings" />
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="myplugin_option_field"><?php esc_html_e( 'Example Option', 'myplugin' ); ?></label>
						</th>
						<td>
							<input type="text" id="myplugin_option_field" name="<?php echo esc_attr( $this->option_name ); ?>[example_option]" value="<?php echo isset( $settings['example_option'] ) ? esc_attr( $settings['example_option'] ) : ''; ?>" class="regular-text" />
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'myplugin' ) );
		}

		if ( ! isset( $_POST['myplugin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['myplugin_nonce'] ) ), 'myplugin_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'myplugin' ) );
		}

		if ( isset( $_POST[ $this->option_name ] ) && is_array( $_POST[ $this->option_name ] ) ) {
			$new_settings_raw = wp_unslash( $_POST[ $this->option_name ] );
			$new_settings     = array();

			if ( isset( $new_settings_raw['example_option'] ) ) {
				$new_settings['example_option'] = $this->validate_example_option( $new_settings_raw['example_option'] );
			}

			update_option( $this->option_name, $new_settings );
		}

		wp_safe_redirect( add_query_arg( 'settings-updated', 'true', menu_page_url( 'myplugin-settings', false ) ) );
		exit;
	}

	private function validate_example_option( $value ) {
		$value = sanitize_text_field( $value );
		// Example strict validation: allow only alphanumeric and dashes/underscores, max length 100
		if ( preg_match( '/^[a-zA-Z0-9_-]{1,100}$/', $value ) ) {
			return $value;
		}
		return '';
	}
}

new MyPlugin_Admin_Page();