public function __construct() {
		$upload_dir_info   = wp_upload_dir();
		$this->upload_dir  = trailingslashit( $upload_dir_info['basedir'] ) . 'plugin-zips/';
		if ( ! file_exists( $this->upload_dir ) ) {
			wp_mkdir_p( $this->upload_dir );
		}
	}

	public function create_zip_from_plugin( $plugin_slug ) {
		// Strict validation: only allow lowercase letters, numbers, dashes and underscores.
		if ( empty( $plugin_slug ) || ! preg_match( '/^[a-z0-9\-_]+$/', $plugin_slug ) ) {
			return new WP_Error( 'invalid_plugin_slug', __( 'Invalid plugin slug.', 'your-textdomain' ) );
		}

		// Build safe plugin dir path.
		if ( ! defined( 'WP_PLUGIN_DIR' ) || ! is_dir( WP_PLUGIN_DIR ) || ! is_readable( WP_PLUGIN_DIR ) ) {
			return new WP_Error( 'plugins_dir_invalid', __( 'The WP_PLUGIN_DIR is not a readable directory.', 'your-textdomain' ) );
		}

		$plugin_dir = trailingslashit( WP_PLUGIN_DIR ) . $plugin_slug;

		// Ensure no path traversal occurred.
		$real_plugin_dir = realpath( $plugin_dir );
		if ( false === $real_plugin_dir || strpos( $real_plugin_dir, realpath( WP_PLUGIN_DIR ) ) !== 0 ) {
			return new WP_Error( 'plugin_not_found', __( 'Plugin directory not found or access denied.', 'your-textdomain' ) );
		}

		if ( ! is_dir( $real_plugin_dir ) ) {
			return new WP_Error( 'plugin_not_found', __( 'Plugin directory not found.', 'your-textdomain' ) );
		}

		$zip_file_path = $this->get_zip_path( $plugin_slug );

		if ( file_exists( $zip_file_path ) ) {
			if ( ! @unlink( $zip_file_path ) ) {
				return new WP_Error( 'zip_delete_failed', __( 'Failed to delete existing ZIP file.', 'your-textdomain' ) );
			}
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'zip_unavailable', __( 'PHP ZipArchive extension is not available.', 'your-textdomain' ) );
		}

		$zip = new ZipArchive();
		$open_result = $zip->open( $zip_file_path, ZipArchive::CREATE );
		if ( true !== $open_result ) {
			return new WP_Error( 'zip_open_failed', sprintf( __( 'Could not create ZIP file. Error code: %d', 'your-textdomain' ), $open_result ) );
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $real_plugin_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $files as $file ) {
			$file_path     = $file->getRealPath();
			$relative_path = $plugin_slug . '/' . substr( $file_path, strlen( $real_plugin_dir ) );

			if ( $file->isDir() ) {
				$zip->addEmptyDir( $relative_path );
			} elseif ( is_readable( $file_path ) ) {
				$zip->addFile( $file_path, $relative_path );
			}
		}

		$zip->close();

		if ( ! file_exists( $zip_file_path ) ) {
			return new WP_Error( 'zip_creation_failed', __( 'ZIP file was not created.', 'your-textdomain' ) );
		}

		return $zip_file_path;
	}

	public function get_zip_path( $plugin_slug ) {
		$sanitized_slug = sanitize_file_name( $plugin_slug );
		return $this->upload_dir . $sanitized_slug . '.zip';
	}
}