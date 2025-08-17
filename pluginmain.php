* @var My_Modular_Secure_Plugin
     */
    private static $instance;

    /**
     * Plugin directory path.
     *
     * @var string
     */
    public $plugin_path;

    /**
     * Plugin directory URL.
     *
     * @var string
     */
    public $plugin_url;

    /**
     * Current plugin version.
     *
     * @var string
     */
    public $version = '1.0.0';

    /**
     * Constructor (private for singleton).
     */
    private function __construct() {
        $this->plugin_path = plugin_dir_path( __FILE__ );
        $this->plugin_url  = plugin_dir_url( __FILE__ );

        $this->load_dependencies();
        $this->initialize_services();

        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
    }

    /**
     * Get singleton instance.
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load plugin text domain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'my-modular-secure-plugin',
            false,
            dirname( plugin_basename( __FILE__ ) ) . '/languages'
        );
    }

    /**
     * Load required dependencies and includes.
     */
    private function load_dependencies() {
        $files = array(
            'includes/class-myplugin-activator.php',
            'includes/class-myplugin-deactivator.php',
            'includes/class-myplugin-service-init.php',
            'includes/class-myplugin-api-key-manager.php',
        );

        foreach ( $files as $file ) {
            $path = $this->plugin_path . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }
    }

    /**
     * Initialize core services of the plugin.
     */
    private function initialize_services() {
        if ( class_exists( 'MyPlugin_Service_Init' ) ) {
            MyPlugin_Service_Init::register_services();
        }
    }

    /**
     * Activation hook.
     */
    public static function activate() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-myplugin-activator.php';
        MyPlugin_Activator::activate();
    }

    /**
     * Deactivation hook.
     */
    public static function deactivate() {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-myplugin-deactivator.php';
        MyPlugin_Deactivator::deactivate();
    }
}

register_activation_hook( __FILE__, array( 'My_Modular_Secure_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'My_Modular_Secure_Plugin', 'deactivate' ) );

My_Modular_Secure_Plugin::get_instance();