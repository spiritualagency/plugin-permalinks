* @var array
     */
    private $admin_notices = array();

    /**
     * Constructor.
     * Hooks into the WordPress lifecycle to display queued notices.
     */
    public function __construct() {
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
        $this->load_persistent_notices();
    }

    /**
     * Queue an admin notice to be displayed and persist until displayed.
     *
     * @param string $message Message to display.
     * @param string $type    Notice type: 'success', 'error', 'warning', 'info'.
     * @param bool   $persist Whether to persist notice across reloads until displayed.
     */
    public function send_admin_notice( $message, $type = 'info', $persist = true ) {
        $allowed_types = array( 'success', 'error', 'warning', 'info' );
        if ( ! in_array( $type, $allowed_types, true ) ) {
            $type = 'info';
        }
        $notice = array(
            'message' => wp_kses( $message, $this->get_allowed_notice_tags() ),
            'type'    => $type
        );
        $this->admin_notices[] = $notice;
        if ( $persist ) {
            $this->persist_notice( $notice );
        }
    }

    /**
     * Display queued admin notices.
     */
    public function display_admin_notices() {
        foreach ( $this->admin_notices as $notice ) {
            printf(
                '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                esc_attr( $notice['type'] ),
                $notice['message']
            );
        }
        $this->admin_notices = array();
        $this->clear_persistent_notices();
    }

    /**
     * Send an email notification.
     *
     * @param string       $subject    Email subject.
     * @param string       $body       Email body.
     * @param string|array $recipients Recipient email or array of emails.
     *
     * @return bool True if the mail was sent, false otherwise.
     */
    public function send_email_notification( $subject, $body, $recipients ) {
        if ( empty( $recipients ) ) {
            return false;
        }
        if ( ! is_array( $recipients ) ) {
            $recipients = array( $recipients );
        }
        $recipients = array_map( 'sanitize_email', $recipients );
        $recipients = array_filter( $recipients, 'is_email' );
        if ( empty( $recipients ) ) {
            return false;
        }
        $headers  = array( 'Content-Type: text/html; charset=UTF-8' );
        $clean_subject = wp_strip_all_tags( $subject );
        $allowed_tags  = $this->get_allowed_email_tags();
        $clean_body    = wp_kses( $body, $allowed_tags );
        return wp_mail( $recipients, $clean_subject, $clean_body, $headers );
    }

    /**
     * Define allowed HTML tags for notices.
     *
     * @return array
     */
    private function get_allowed_notice_tags() {
        return array(
            'a'      => array(
                'href'   => array(),
                'title'  => array(),
                'target' => array(),
            ),
            'br'     => array(),
            'em'     => array(),
            'strong' => array(),
            'p'      => array(),
            'span'   => array(
                'class' => array()
            ),
            'ul'     => array(),
            'ol'     => array(),
            'li'     => array(),
        );
    }

    /**
     * Define allowed HTML tags for email bodies.
     *
     * @return array
     */
    private function get_allowed_email_tags() {
        $tags = wp_kses_allowed_html( 'post' );
        return $tags;
    }

    /**
     * Persist a notice in the options table.
     *
     * @param array $notice
     */
    private function persist_notice( $notice ) {
        $stored = get_option( 'permalinkpublisher_persistent_notices', array() );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }
        $stored[] = $notice;
        update_option( 'permalinkpublisher_persistent_notices', $stored, false );
    }

    /**
     * Load any persistent notices into the current instance.
     */
    private function load_persistent_notices() {
        $stored = get_option( 'permalinkpublisher_persistent_notices', array() );
        if ( is_array( $stored ) && ! empty( $stored ) ) {
            foreach ( $stored as $notice ) {
                if ( isset( $notice['message'], $notice['type'] ) ) {
                    $this->admin_notices[] = $notice;
                }
            }
        }
    }

    /**
     * Clear all persistent notices after they are displayed.
     */
    private function clear_persistent_notices() {
        delete_option( 'permalinkpublisher_persistent_notices' );
    }
}