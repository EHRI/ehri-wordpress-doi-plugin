<?php
/**
 * DOI Metadata Manager - Admin Panel
 *
 * Adds an admin settings page to configure the DOI Metadata plugin
 *
 * @package ehri-pid-tools
 */

/**
 * EHRI DOI Metadata Admin Class
 */
class EHRI_DOI_Metadata_Admin {
	/**
	 * The option prefix for the settings.
	 *
	 * @var string The prefix for the options stored in the database.
	 */
	private string $option_prefix = '_ehri_doi_plugin';

	/**
	 * The options array.
	 *
	 * @var array The options array.
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * Initializes the admin settings page and registers the settings.
	 */
	public function __construct() {
		// Add admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Register settings.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Enqueue admin CSS.
		add_action(
			'admin_enqueue_scripts',
			function() {
				wp_enqueue_style(
					'ehri-doi-metadata-admin',
					plugins_url( 'css/ehri-doi-admin.css', EHRI_DOI_PLUGIN_PATH ),
					array(),
					filemtime( plugin_dir_path( EHRI_DOI_PLUGIN_PATH ) . 'css/ehri-doi-admin.css' )
				);
			}
		);

		// Load options.
		$this->options = get_option(
			$this->option_prefix . '_options',
			array(
				'publisher'           => '',
				'service_url'         => 'https://api.datacite.org/dois',
				'resolver_url_prefix' => 'https://doi.org/',
				'prefix'              => '', // Default DOI prefix.
				'client_id'           => '',
				'client_secret'       => '',
			)
		);
	}

	/**
	 * Add admin menu for the DOI Metadata settings page.
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'DOI Metadata Settings', 'edmp' ),
			__( 'DOI Metadata', 'edmp' ),
			'manage_options',
			'ehri-doi-metadata-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings for the DOI Metadata plugin.
	 */
	public function register_settings() {
		// Register a single option to store all our settings.
		register_setting(
			'doi_metadata_settings',
			$this->option_prefix . '_options',
			array( $this, 'sanitize_settings' )
		);

		// Publisher Settings Section.
		add_settings_section(
			'doi_publisher_section',
			__( 'Publisher Information', 'edmp' ),
			array( $this, 'publisher_section_callback' ),
			'ehri-doi-metadata-settings'
		);

		add_settings_field(
			'publisher',
			__( 'Publisher Name', 'edmp' ),
			array( $this, 'publisher_field_callback' ),
			'ehri-doi-metadata-settings',
			'doi_publisher_section'
		);

		add_settings_field(
			'prefix',
			__( 'DOI Prefix', 'edmp' ),
			array( $this, 'prefix_field_callback' ),
			'ehri-doi-metadata-settings',
			'doi_publisher_section'
		);

		// DOI Provider Settings Section.
		add_settings_section(
			'doi_provider_section',
			__( 'DOI Provider Settings', 'edmp' ),
			array( $this, 'provider_section_callback' ),
			'ehri-doi-metadata-settings'
		);

		add_settings_field(
			'service_url',
			__( 'DOI Registration Service', 'edmp' ),
			array( $this, 'service_url_field_callback' ),
			'ehri-doi-metadata-settings',
			'doi_provider_section'
		);

		add_settings_field(
			'resolver_url_prefix',
			__( 'Resolver URL Prefix', 'edmp' ),
			array( $this, 'resolver_url_prefix_field_callback' ),
			'ehri-doi-metadata-settings',
			'doi_provider_section'
		);

		// DOI Provider Credentials Section.
		add_settings_section(
			'doi_provider_credentials_section',
			__( 'DOI Provider Credentials', 'edmp' ),
			array( $this, 'provider_section_callback' ),
			'ehri-doi-metadata-settings'
		);

		add_settings_field(
			'client_id',
			__( 'DOI Service Client ID', 'edmp' ),
			array( $this, 'client_id_field_callback' ),
			'ehri-doi-metadata-settings',
			'doi_provider_credentials_section'
		);

		add_settings_field(
			'client_secret',
			__( 'DOI Service Client Secret', 'edmp' ),
			array( $this, 'client_secret_field_callback' ),
			'ehri-doi-metadata-settings',
			'doi_provider_credentials_section',
			array( 'type' => 'password' )
		);

		// Testing section.
		add_settings_section(
			'doi_testing_section',
			__( 'Testing Parameters', 'edmp' ),
			array( $this, 'testing_section_callback' ),
			'ehri-doi-metadata-settings'
		);

		add_settings_field(
			'fake_citation_doi',
			__( 'Placeholder test DOI', 'edmp' ),
			array( $this, 'fake_citation_doi_field_callback' ),
			'ehri-doi-metadata-settings',
			'doi_testing_section'
		);
	}

	/**
	 * Sanitize the settings input.
	 *
	 * @param array $input The input settings.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		$output = array();

		// Sanitize publisher.
		$output['publisher'] = sanitize_text_field( $input['publisher'] );

		// Sanitize DOI prefix.
		$output['prefix'] = sanitize_text_field( $input['prefix'] );

		// Sanitize provider URLs.
		$output['service_url']         = esc_url_raw( $input['service_url'] );
		$output['resolver_url_prefix'] = esc_url_raw( $input['resolver_url_prefix'] );

		// Sanitize credentials.
		$output['client_id']     = sanitize_text_field( $input['client_id'] );
		$output['client_secret'] = sanitize_text_field( $input['client_secret'] );

		// Testing parameters.
		$output['fake_citation_doi'] = sanitize_text_field( $input['fake_citation_doi'] );

		return $output;
	}

	/**
	 * Callback for the publisher section.
	 */
	public function publisher_section_callback() {
		echo '<p>' . esc_html__( 'Configure the DOI publisher information that will be used for all DOIs generated by this plugin.', 'edmp' ) . '</p>';
	}

	/**
	 * Callback for the provider section.
	 */
	public function provider_section_callback() {
		echo '<p>' . esc_html__( 'Configure the DOI provider URLs for both production and test environments.', 'edmp' ) . '</p>';
	}

	/**
	 * Callback for the testing section.
	 */
	public function testing_section_callback() {
		echo '<p>' . esc_html__( 'Settings for testing the plugin with sandbox DOIs.', 'edmp' ) . '</p>';
	}

	/**
	 * Callback for the publisher field.
	 */
	public function publisher_field_callback() {
		$value = $this->options['publisher'] ?? '';
		echo sprintf(
			'<input type="text" id="publisher" name="%s_options[publisher]" value="%s" class="regular-text" />',
			esc_attr( $this->option_prefix ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'The name of the organization or publisher responsible for issuing DOIs.', 'edmp' ) . '</p>';
	}

	/**
	 * Callback for the prefix field.
	 */
	public function prefix_field_callback() {
		$value = $this->options['prefix'] ?? '';
		echo sprintf(
			'<input type="text" id="prefix" name="%s_options[prefix]" value="%s" />',
			esc_attr( $this->option_prefix ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'Your registered DOI prefix (e.g., 10.1234)', 'edmp' ) . '</p>';
	}

	/**
	 * Callback for the service URL field.
	 */
	public function service_url_field_callback() {
		$value = $this->options['service_url'] ?? '';
		echo sprintf(
			'<input type="url" id="service_url" name="%s_options[service_url]" value="%s" class="regular-text" />',
			esc_attr( $this->option_prefix ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'The base URL for the DOI registration service', 'edmp' ) . '</p>';
	}

	/**
	 * Callback for resolver URL prefix field.
	 */
	public function resolver_url_prefix_field_callback() {
		$value = $this->options['resolver_url_prefix'] ?? 'https://doi.org/';
		echo sprintf(
			'<input type="url" id="resolver_url_prefix" name="%s_options[resolver_url_prefix]" value="%s" class="regular-text" />',
			esc_attr( $this->option_prefix ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'The base URL for resolving DOIs', 'edmp' ) . '</p>';
	}

	/**
	 * Callback for the client ID field.
	 */
	public function client_id_field_callback() {
		$value = $this->options['client_id'] ?? '';
		echo sprintf(
			'<input type="text" id="client_id" name="%s_options[client_id]" value="%s" class="regular-text" />',
			esc_attr( $this->option_prefix ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'The client ID for the DOI service', 'edmp' ) . '</p>';
	}

	/**
	 * Callback for the client secret field.
	 */
	public function client_secret_field_callback() {
		$value = $this->options['client_secret'] ?? '';
		echo sprintf(
			'<input type="password" id="client_secret" name="%s_options[client_secret]" value="%s" class="regular-text" />',
			esc_attr( $this->option_prefix ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'The secret key for the DOI service', 'edmp' ) . '</p>';
	}

	/**
	 * Callback for the client ID field.
	 */
	public function fake_citation_doi_field_callback() {
		$value = $this->options['fake_citation_doi'] ?? '';
		echo sprintf(
			'<input type="text" id="fake_citation_doi" name="%s_options[fake_citation_doi]" value="%s" class="regular-text" />',
			esc_attr( $this->option_prefix ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'A placeholder (real) DOI for testing the citation widget when using placeholder DOIs that don\'t resolve on https://doi.org/', 'edmp' ) . '</p>';
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'doi_metadata_settings' );
				do_settings_sections( 'ehri-doi-metadata-settings' );
				submit_button();
				?>
			</form>
			<hr/>

			<h2><?php esc_html_e( 'Currently registered DOIs', 'edmp' ); ?></h2>
			<p><?php esc_html_e( 'The following DOIs are currently registered with the DataCite API.', 'edmp' ); ?></p
			<div class="textarea-wrap">
				<label style="display: none" for="doi-report"><?php esc_html_e( 'DOI Report', 'edmp' ); ?></label>
				<textarea id="doi-report" rows="8" readonly><?php echo esc_html( $this->get_doi_report_data() ); ?>
				</textarea>
			</div>
		</div>
		<?php
	}

	/**
	 * Get the options as an array.
	 *
	 * @return array The options array.
	 */
	public function get_options(): array {
		return $this->options;
	}

	/**
	 * Get the URL to the DOI registration service.
	 *
	 * @return string The URL to e.g. the DataCite REST API /dois endpoint.
	 */
	public function get_service_url(): string {
		return $this->options['service_url'];
	}

	/**
	 * Get the client ID.
	 */
	public function get_client_id(): string {
		return $this->options['client_id'];
	}

	/**
	 * Get the client secret.
	 */
	public function get_client_secret(): string {
		return $this->options['client_secret'];
	}

	/**
	 * Fetch CSV data for the currently registered DOIs and the post permalink.
	 * This is mostly for debugging/dev purposes.
	 */
	public function get_doi_report_data(): string {
		$args = array(
			'post_type'      => 'any',
			'posts_per_page' => -1,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'     => '_doi',
					'compare' => 'EXISTS',
				),
			),
		);

		// Run the query.
		$meta_query = new WP_Query( $args );

		$out = '';
		// Check if posts were found.
		if ( $meta_query->have_posts() ) {
			while ( $meta_query->have_posts() ) {
				$meta_query->the_post();
				$doi       = get_post_meta( get_the_ID(), '_doi', true );
				$permalink = get_permalink( get_the_ID() );
				$out      .= sprintf(
					'%s,%s' . PHP_EOL,
					esc_html( $doi ),
					esc_html( $permalink )
				);
			}

			// Restore original post data.
			wp_reset_postdata();
		}
		return $out;
	}
}
