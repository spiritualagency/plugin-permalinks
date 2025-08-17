public function __construct( $config = array() ) {
        $upload_dir = wp_upload_dir();
        $defaults   = array(
            'base_path' => trailingslashit( $upload_dir['basedir'] ) . 'custom-storage/',
            'base_url'  => trailingslashit( $upload_dir['baseurl'] ) . 'custom-storage/',
        );

        $config           = wp_parse_args( $config, $defaults );
        $this->base_path  = trailingslashit( $config['base_path'] );
        $this->base_url   = trailingslashit( $config['base_url'] );

        if ( ! wp_mkdir_p( $this->base_path ) ) {
            throw new RuntimeException( sprintf( 'Unable to create storage directory: %s', esc_html( $this->base_path ) ) );
        }
    }

    protected function sanitize_and_validate_path( $path ) {
        $decoded = rawurldecode( $path );
        $normalized = wp_normalize_path( $decoded );
        $full_path = trailingslashit( $this->base_path ) . ltrim( $normalized, '/\\' );
        $real_base = realpath( $this->base_path );
        $real_full = realpath( dirname( $full_path ) );
        if ( $real_base === false || $real_full === false || strpos( $real_full, $real_base ) !== 0 ) {
            throw new InvalidArgumentException( 'Invalid path specified.' );
        }
        return $full_path;
    }

    public function upload( $file_path, $destination ) {
        if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
            throw new InvalidArgumentException( 'Source file does not exist or is not readable.' );
        }

        $target_path = $this->sanitize_and_validate_path( $destination );

        if ( file_exists( $target_path ) ) {
            // generate unique filename to avoid overwrite
            $path_info = pathinfo( $target_path );
            $unique_target = $path_info['dirname'] . '/' . uniqid( '', true ) . '-' . $path_info['basename'];
            $target_path = $unique_target;
        }

        if ( ! wp_mkdir_p( dirname( $target_path ) ) ) {
            throw new RuntimeException( sprintf( 'Failed to create destination directory: %s', esc_html( dirname( $target_path ) ) ) );
        }

        if ( ! copy( $file_path, $target_path ) ) {
            throw new RuntimeException( 'Failed to copy file to destination.' );
        }

        $relative_path = ltrim( str_replace( $this->base_path, '', $target_path ), '/\\' );
        return $relative_path;
    }

    public function get_public_url( $file_identifier ) {
        $decoded = rawurldecode( $file_identifier );
        $relative = ltrim( wp_normalize_path( $decoded ), '/\\' );
        $url = trailingslashit( $this->base_url ) . rawurlencode( $relative );
        return esc_url( $url );
    }

    public function delete( $file_identifier ) {
        try {
            $file_path = $this->sanitize_and_validate_path( $file_identifier );
        } catch ( InvalidArgumentException $e ) {
            return false;
        }

        if ( file_exists( $file_path ) && is_writable( $file_path ) && is_file( $file_path ) ) {
            return unlink( $file_path );
        }
        return false;
    }
}