* @var array
	 */
	private $config;

	/**
	 * @var S3Client
	 */
	private $client;

	/**
	 * Constructor.
	 *
	 * @param array $config {
	 *     @type string $key       AWS Access Key ID.
	 *     @type string $secret    AWS Secret Access Key.
	 *     @type string $region    AWS Region.
	 *     @type string $bucket    S3 Bucket name.
	 *     @type string $endpoint  (Optional) Custom endpoint.
	 *     @type bool   $public    (Optional) Whether uploaded files are public.
	 * }
	 * @throws InvalidArgumentException If required config values are missing or invalid.
	 */
	public function __construct( $config ) {
		$defaults    = array(
			'key'      => '',
			'secret'   => '',
			'region'   => '',
			'bucket'   => '',
			'endpoint' => '',
			'public'   => false,
		);
		$parsed      = wp_parse_args( $config, $defaults );
		$this->config = array(
			'key'      => sanitize_text_field( $parsed['key'] ),
			'secret'   => sanitize_text_field( $parsed['secret'] ),
			'region'   => sanitize_text_field( $parsed['region'] ),
			'bucket'   => sanitize_text_field( $parsed['bucket'] ),
			'endpoint' => esc_url_raw( $parsed['endpoint'] ),
			'public'   => (bool) $parsed['public'],
		);

		if ( empty( $this->config['key'] ) || empty( $this->config['secret'] ) || empty( $this->config['region'] ) || empty( $this->config['bucket'] ) ) {
			throw new InvalidArgumentException( 'Missing required AWS S3 configuration parameters.' );
		}

		if ( ! class_exists( '\Aws\S3\S3Client' ) ) {
			throw new RuntimeException( 'AWS SDK not found. Please ensure aws/aws-sdk-php is installed and loaded.' );
		}

		$this->authenticate();
	}

	/**
	 * Authenticate and initialize S3 client.
	 *
	 * @return void
	 */
	private function authenticate() {
		$args = array(
			'version'     => 'latest',
			'region'      => $this->config['region'],
			'credentials' => array(
				'key'    => $this->config['key'],
				'secret' => $this->config['secret'],
			),
		);

		if ( ! empty( $this->config['endpoint'] ) ) {
			$args['endpoint'] = $this->config['endpoint'];
		}

		$this->client = new S3Client( $args );
	}

	/**
	 * Uploads a file to S3.
	 *
	 * @param string $file_path   Path to the local file.
	 * @param string $destination Destination path in S3 bucket.
	 * @return bool|string        S3 object key on success, false on failure.
	 */
	public function upload( $file_path, $destination ) {
		if ( ! is_string( $file_path ) || ! is_file( $file_path ) || ! is_readable( $file_path ) || filesize( $file_path ) === 0 ) {
			return false;
		}

		try {
			$params = array(
				'Bucket'     => $this->config['bucket'],
				'Key'        => ltrim( $destination, '/' ),
				'SourceFile' => $file_path,
			);

			if ( $this->config['public'] ) {
				$params['ACL'] = 'public-read';
			}

			$this->client->putObject( $params );

			return $params['Key'];

		} catch ( AwsException $e ) {
			error_log( 'S3 Upload Error: ' . $e->getAwsErrorMessage() );
			return false;
		} catch ( \Exception $e ) {
			error_log( 'S3 Upload General Error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get the public URL for a file in S3.
	 *
	 * @param string $file_identifier The file key in S3.
	 * @return string|false           The public URL or false on failure.
	 */
	public function get_public_url( $file_identifier ) {
		if ( empty( $file_identifier ) ) {
			return false;
		}
		try {
			if ( $this->config['public'] ) {
				return $this->client->getObjectUrl( $this->config['bucket'], $file_identifier );
			}

			$cmd     = $this->client->getCommand(
				'GetObject',
				array(
					'Bucket' => $this->config['bucket'],
					'Key'    => $file_identifier,
				)
			);
			$request = $this->client->createPresignedRequest( $cmd, '+15 minutes' );
			return (string) $request->getUri();

		} catch ( AwsException $e ) {
			error_log( 'S3 Get URL Error: ' . $e->getAwsErrorMessage() );
			return false;
		} catch ( \Exception $e ) {
			error_log( 'S3 Get URL General Error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Deletes a file from S3.
	 *
	 * @param string $file_identifier The file key in S3.
	 * @return bool                   True on success, false on failure.
	 */
	public function delete( $file_identifier ) {
		if ( empty( $file_identifier ) ) {
			return false;
		}
		try {
			$this->client->deleteObject(
				array(
					'Bucket' => $this->config['bucket'],
					'Key'    => $file_identifier,
				)
			);
			return true;
		} catch ( AwsException $e ) {
			error_log( 'S3 Delete Error: ' . $e->getAwsErrorMessage() );
			return false;
		} catch ( \Exception $e ) {
			error_log( 'S3 Delete General Error: ' . $e->getMessage() );
			return false;
		}
	}
}