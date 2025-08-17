public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route(
            'myplugin/v1',
            '/permalink/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'handle_get_permalink' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'id' => array(
                        'validate_callback' => array( $this, 'validate_id' ),
                    ),
                ),
            )
        );

        register_rest_route(
            'myplugin/v1',
            '/metadata/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'handle_get_metadata' ),
                'permission_callback' => array( $this, 'permission_check' ),
                'args'                => array(
                    'id' => array(
                        'validate_callback' => array( $this, 'validate_id' ),
                    ),
                ),
            )
        );
    }

    public function validate_id( $param, $request, $key ) {
        $id = absint( $param );
        return $id > 0;
    }

    public function permission_check( $request ) {
        $post_id = absint( $request['id'] );
        if ( ! $post_id ) {
            return false;
        }
        $post = get_post( $post_id );
        if ( ! $post ) {
            return false;
        }
        // Use specific capability for viewing individual post
        return current_user_can( 'read_post', $post_id );
    }

    protected function is_allowed_post_status( $post_id ) {
        $status = get_post_status( $post_id );
        $allowed_statuses = apply_filters( 'myplugin_allowed_post_statuses', array( 'publish' ) );
        return in_array( $status, $allowed_statuses, true );
    }

    public function handle_get_permalink( $request ) {
        $post_id = absint( $request['id'] );

        if ( ! $post_id || ! $this->is_allowed_post_status( $post_id ) ) {
            return new WP_Error( 'invalid_post', __( 'Invalid or inaccessible post ID.', 'myplugin' ), array( 'status' => 404 ) );
        }

        $permalink = get_permalink( $post_id );
        if ( ! $permalink ) {
            return new WP_Error( 'no_permalink', __( 'Could not retrieve permalink.', 'myplugin' ), array( 'status' => 500 ) );
        }

        return rest_ensure_response(
            array(
                'id'        => $post_id,
                'permalink' => esc_url_raw( $permalink ),
            )
        );
    }

    protected function filter_public_metadata( $meta ) {
        $public_meta = array();
        foreach ( $meta as $key => $value ) {
            if ( is_protected_meta( $key, 'post' ) ) {
                continue;
            }
            $public_meta[ $key ] = $value;
        }
        return $public_meta;
    }

    public function handle_get_metadata( $request ) {
        $post_id = absint( $request['id'] );

        if ( ! $post_id || ! $this->is_allowed_post_status( $post_id ) ) {
            return new WP_Error( 'invalid_post', __( 'Invalid or inaccessible post ID.', 'myplugin' ), array( 'status' => 404 ) );
        }

        $meta = get_post_meta( $post_id );
        $meta = $this->filter_public_metadata( $meta );

        if ( empty( $meta ) ) {
            return new WP_Error( 'no_metadata', __( 'No public metadata found.', 'myplugin' ), array( 'status' => 404 ) );
        }

        return rest_ensure_response(
            array(
                'id'       => $post_id,
                'metadata' => $meta,
            )
        );
    }
}