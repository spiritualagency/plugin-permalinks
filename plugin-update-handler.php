public function __construct() {
            $this->register_hooks();
        }

        private function register_hooks() {
            add_action( 'upgrader_process_complete', array( $this, 'on_plugin_update' ), 10, 2 );
        }

        public function on_plugin_update( $upgrader_object, $options ) {
            if ( ! isset( $options['type'] ) || $options['type'] !== 'plugin' ) {
                return;
            }

            if ( empty( $options['action'] ) || $options['action'] !== 'update' ) {
                return;
            }

            if ( ! isset( $options['plugins'] ) || ! is_array( $options['plugins'] ) ) {
                return;
            }

            foreach ( $options['plugins'] as $plugin ) {
                $this->process_updated_plugin( $plugin );
            }
        }

        protected function process_updated_plugin( $plugin ) {
            if ( ! function_exists( 'get_plugin_data' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
            if ( ! file_exists( $plugin_file ) ) {
                return;
            }

            $plugin_data = get_plugin_data( $plugin_file );

            do_action( 'plugin_update_handler_process', $plugin, $plugin_data );

            if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
                $name    = isset( $plugin_data['Name'] ) ? sanitize_text_field( $plugin_data['Name'] ) : sanitize_text_field( $plugin );
                $version = isset( $plugin_data['Version'] ) ? sanitize_text_field( $plugin_data['Version'] ) : 'unknown';

                error_log( sprintf(
                    'Plugin updated: %s (Version: %s)',
                    $name,
                    $version
                ) );
            }
        }
    }
}

new Plugin_Update_Handler();