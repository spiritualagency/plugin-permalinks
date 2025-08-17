public function upload( $file_path, $destination ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		// Sanitize destination and prevent directory traversal.
		$destination = wp_normalize_path( $destination );
		$destination = ltrim( $destination, '/\\' );
		if ( strpos( $destination, '..' ) !== false ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return false;
		}

		$target_dir = trailingslashit( $upload_dir['basedir'] ) . $destination;
		$target_dir = wp_normalize_path( $target_dir );

		// Ensure target is within uploads.
		$real_upload_basedir = realpath( $upload_dir['basedir'] );
		$real_target_dir     = realpath( dirname( $target_dir ) ) ?: dirname( $target_dir );
		if ( strpos( $real_target_dir, $real_upload_basedir ) !== 0 ) {
			return false;
		}

		// Prevent overwriting existing files.
		if ( file_exists( $target_dir ) ) {
			return false;
		}

		wp_mkdir_p( dirname( $target_dir ) );

		// Use WordPress sideload handler to pass through security hooks.
		$tmp_file_array = array(
			'name'     => basename( $destination ),
			'tmp_name' => $file_path,
		);
		$overrides      = array(
			'test_form'   => false,
			'test_size'   => false,
			'test_upload' => false,
		);

		$movefile = wp_handle_sideload( $tmp_file_array, $overrides );
		if ( isset( $movefile['error'] ) ) {
			return false;
		}

		$file_identifier = $this->generate_identifier( $destination );
		return $file_identifier;
	}

	/**
	 * Get the public URL for a stored file.
	 *
	 * @param string $file_identifier Unique file identifier.
	 * @return string|false Public URL or false on failure.
	 */
	public function get_public_url( $file_identifier ) {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return false;
		}

		$file_path = $this->identifier_to_path( $file_identifier );
		if ( ! $file_path ) {
			return false;
		}

		$url = trailingslashit( $upload_dir['baseurl'] ) . ltrim( $file_path, '/\\' );
		return esc_url( $url );
	}

	/**
	 * Delete a stored file.
	 *
	 * @param string $file_identifier Unique file identifier.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $file_identifier ) {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return false;
		}

		$file_path = $this->identifier_to_path( $file_identifier );
		if ( ! $file_path ) {
			return false;
		}

		$full_path = trailingslashit( $upload_dir['basedir'] ) . ltrim( $file_path, '/\\' );
		$full_path = wp_normalize_path( $full_path );

		// Ensure deletion target is within uploads directory.
		$real_upload_basedir = realpath( $upload_dir['basedir'] );
		$real_full_path      = realpath( $full_path );
		if ( $real_full_path === false || strpos( $real_full_path, $real_upload_basedir ) !== 0 ) {
			return false;
		}

		if ( file_exists( $full_path ) && is_file( $full_path ) ) {
			return unlink( $full_path );
		}

		return false;
	}

	/**
	 * Generate a unique file identifier based on destination.
	 *
	 * @param string $destination Destination path.
	 * @return string Identifier.
	 */
	protected function generate_identifier( $destination ) {
		$clean_path = ltrim( wp_normalize_path( $destination ), '/\\' );
		// Include secret salt for unpredictability.
		$secret = wp_salt( 'nonce' );
		return md5( $secret . '|' . $clean_path ) . ':' . $clean_path;
	}

	/**
	 * Convert an identifier back to relative file path.
	 *
	 * @param string $identifier File identifier.
	 * @return string|false Relative path on success, false on failure.
	 */
	protected function identifier_to_path( $identifier ) {
		$parts = explode( ':', $identifier, 2 );
		if ( count( $parts ) === 2 ) {
			$secret = wp_salt( 'nonce' );
			if ( md5( $secret . '|' . $parts[1] ) === $parts[0] ) {
				return $parts[1];
			}
		}
		return false;
	}
}