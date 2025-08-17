public function __construct( $config = array() ) {
			$defaults     = array(
				'client_id'        => '',
				'client_secret'    => '',
				'redirect_uri'     => '',
				'refresh_token'    => '',
				'application_name' => 'WordPress GDrive Storage',
			);
			$this->config = wp_parse_args( $this->sanitize_config( $config ), $defaults );
			$this->authenticate();
		}

		protected function sanitize_config( $config ) {
			$sanitized = array();
			foreach ( $config as $key => $value ) {
				if ( in_array( $key, array( 'client_id', 'client_secret', 'redirect_uri', 'refresh_token', 'application_name' ), true ) ) {
					$sanitized[ $key ] = is_string( $value ) ? trim( sanitize_text_field( $value ) ) : '';
				}
			}
			return $sanitized;
		}

		protected function authenticate() {
			if ( ! class_exists( 'Google_Client' ) ) {
				require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
			}

			$this->client = new Google_Client();
			$this->client->setClientId( $this->config['client_id'] );
			$this->client->setClientSecret( $this->config['client_secret'] );
			$this->client->setRedirectUri( $this->config['redirect_uri'] );
			$this->client->setApplicationName( $this->config['application_name'] );
			$this->client->addScope( Google_Service_Drive::DRIVE );
			$this->client->setAccessType( 'offline' );

			if ( ! empty( $this->config['refresh_token'] ) ) {
				try {
					$this->client->fetchAccessTokenWithRefreshToken( $this->config['refresh_token'] );
				} catch ( Exception $e ) {
					error_log( 'Google Drive authentication error: ' . $e->getMessage() );
					return new WP_Error( 'gdrive_auth_error', __( 'Google Drive authentication failed.', 'your-text-domain' ) );
				}
			}

			$this->service = new Google_Service_Drive( $this->client );
			return true;
		}

		public function upload( $file_path, $destination = '' ) {
			if ( ! file_exists( $file_path ) ) {
				return new WP_Error( 'file_not_found', __( 'File not found.', 'your-text-domain' ) );
			}

			$file = new Google_Service_Drive_DriveFile();
			$file->setName( basename( $file_path ) );
			if ( ! empty( $destination ) ) {
				$file->setParents( array( $destination ) );
			}

			$mime_type = mime_content_type( $file_path );

			// Stream the file to Google Drive to avoid memory exhaustion.
			try {
				$contentStream = fopen( $file_path, 'rb' );
				$createdFile   = $this->service->files->create(
					$file,
					array(
						'data'       => $contentStream,
						'mimeType'   => $mime_type,
						'uploadType' => 'resumable',
					)
				);
				fclose( $contentStream );
				return $createdFile->id;
			} catch ( Exception $e ) {
				return new WP_Error( 'upload_error', $e->getMessage() );
			}
		}

		public function get_public_url( $file_identifier, $confirm = false ) {
			if ( ! $confirm ) {
				return new WP_Error( 'permission_not_granted', __( 'Public access not confirmed.', 'your-text-domain' ) );
			}

			try {
				$permission = new Google_Service_Drive_Permission();
				$permission->setType( 'anyone' );
				$permission->setRole( 'reader' );
				$this->service->permissions->create( $file_identifier, $permission );

				$file = $this->service->files->get(
					$file_identifier,
					array(
						'fields' => 'webViewLink,webContentLink',
					)
				);

				return ! empty( $file->webViewLink ) ? esc_url_raw( $file->webViewLink ) : '';
			} catch ( Exception $e ) {
				return new WP_Error( 'get_public_url_error', $e->getMessage() );
			}
		}

		public function delete( $file_identifier ) {
			try {
				$this->service->files->delete( $file_identifier );
				return true;
			} catch ( Exception $e ) {
				return new WP_Error( 'delete_error', $e->getMessage() );
			}
		}
	}
}