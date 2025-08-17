private static function get_secret_key() {
        if ( defined( 'PERMALINK_PUBLISHER_SECRET_KEY' ) && PERMALINK_PUBLISHER_SECRET_KEY ) {
            return PERMALINK_PUBLISHER_SECRET_KEY;
        }
        $secret_key_encrypted = get_option( self::$secret_key_option );
        if ( ! $secret_key_encrypted ) {
            $generated_key = wp_generate_password( 64, true, true );
            if ( function_exists( 'wp_encrypt' ) ) {
                $secret_key_encrypted = wp_encrypt( $generated_key );
            } else {
                $secret_key_encrypted = base64_encode( $generated_key ); // fallback minimal obfuscation
            }
            update_option( self::$secret_key_option, $secret_key_encrypted, false );
        }
        if ( function_exists( 'wp_decrypt' ) ) {
            $secret_key = wp_decrypt( $secret_key_encrypted );
        } else {
            $secret_key = base64_decode( $secret_key_encrypted );
        }
        return $secret_key;
    }

    /**
     * Generate a token with resource ID and expiry time
     */
    public static function generate_token( $resource_id, $expiry ) {
        $expiry_ts = is_numeric( $expiry ) ? intval( $expiry ) : strtotime( $expiry );
        if ( ! $expiry_ts ) {
            return false;
        }
        $payload = array(
            'rid' => (string) $resource_id,
            'exp' => $expiry_ts
        );
        $payload_json = wp_json_encode( $payload );
        $signature = hash_hmac( 'sha256', $payload_json, self::get_secret_key() );
        $token = rtrim( strtr( base64_encode( $payload_json . '::' . $signature ), '+/', '-_' ), '=' );
        return $token;
    }

    /**
     * Validate a token and ensure it matches the expected resource ID
     */
    public static function validate_token( $token, $expected_resource_id = null ) {
        $decoded = base64_decode( strtr( $token, '-_', '+/' ), true );
        if ( ! $decoded || strpos( $decoded, '::' ) === false ) {
            return false;
        }
        list( $payload_json, $signature ) = explode( '::', $decoded, 2 );
        $expected_signature = hash_hmac( 'sha256', $payload_json, self::get_secret_key() );
        if ( ! hash_equals( $expected_signature, $signature ) ) {
            return false;
        }
        $payload = json_decode( $payload_json, true );
        if ( ! is_array( $payload ) || empty( $payload['exp'] ) || time() > intval( $payload['exp'] ) ) {
            return false;
        }
        if ( $expected_resource_id !== null && (string) $payload['rid'] !== (string) $expected_resource_id ) {
            return false;
        }
        return $payload;
    }

    /**
     * Generate a signed URL containing a secure token
     */
    public static function generate_signed_url( $url, $expiry ) {
        $parsed = wp_parse_url( $url );
        if ( empty( $parsed['path'] ) ) {
            return false;
        }
        $resource_id = md5( $parsed['path'] );
        $token = self::generate_token( $resource_id, $expiry );
        if ( ! $token ) {
            return false;
        }
        $glue = strpos( $url, '?' ) !== false ? '&' : '?';
        return esc_url_raw( $url . $glue . 'token=' . rawurlencode( $token ) );
    }
}