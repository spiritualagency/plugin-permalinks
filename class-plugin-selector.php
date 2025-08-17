* @var string
     */
    protected $option_name;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->option_name = 'permalink_publisher_selected_plugins';
        add_action( 'admin_init', array( $this, 'maybe_save_selected_plugins' ) );
    }

    /**
     * Get available plugins.
     *
     * @return array
     */
    public function get_available_plugins() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return get_plugins();
    }

    /**
     * Get selected plugins from options.
     *
     * @return array
     */
    public function get_selected_plugins() {
        $selected = get_option( $this->option_name, array() );
        if ( ! is_array( $selected ) ) {
            $selected = array();
        }
        return $selected;
    }

    /**
     * Save selected plugins to options.
     *
     * @param array $plugins List of plugin slugs.
     */
    public function save_selected_plugins( $plugins ) {
        if ( ! is_array( $plugins ) ) {
            $plugins = array();
        }
        $available     = array_keys( $this->get_available_plugins() );
        $valid_plugins = array_intersect( $plugins, $available );
        update_option( $this->option_name, $valid_plugins );
    }

    /**
     * Process saving of selected plugins if request is valid.
     */
    protected function maybe_save_selected_plugins() {
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            if ( isset( $_POST['plugin_selector_nonce'] ) && wp_verify_nonce( wp_unslash( $_POST['plugin_selector_nonce'] ), 'plugin_selector_save' ) ) {
                if ( current_user_can( 'activate_plugins' ) ) {
                    $selected         = isset( $_POST['selected_plugins'] ) ? (array) $_POST['selected_plugins'] : array();
                    $selected         = array_map( 'sanitize_file_name', wp_unslash( $selected ) );
                    $available        = array_keys( $this->get_available_plugins() );
                    $selected         = array_intersect( $selected, $available );
                    $this->save_selected_plugins( $selected );
                }
            }
        }
    }
}