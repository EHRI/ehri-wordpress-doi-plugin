<?php
/**
 * DOI Metadata Manager
 *
 * @package ehri-pid-tools
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Include admin panel functionality.
require_once EHRI_DOI_PLUGIN_DIR . 'includes/admin/class-ehri-doi-metadata-admin.php';

// Include DOI renderer.
require_once EHRI_DOI_PLUGIN_DIR . 'includes/class-ehri-doi-metadata-renderer.php';

// Include citation widget.
require_once EHRI_DOI_PLUGIN_DIR . 'includes/class-ehri-doi-citation-widget.php';

// Include DOI URL widget.
require_once EHRI_DOI_PLUGIN_DIR . 'includes/class-ehri-doi-url-widget.php';

// Include DOI metadata helpers.
require_once EHRI_DOI_PLUGIN_DIR . 'includes/class-ehri-doi-metadata-helpers.php';

// Include DOI repository.
require_once EHRI_DOI_PLUGIN_DIR . 'includes/class-ehri-doi-repository.php';


/**
 * EHRI DOI Metadata Manager.
 */
class EHRI_DOI_Metadata_Manager {

	/**
	 * Admin panel data helpers.
	 *
	 * @var EHRI_DOI_Metadata_Admin
	 */
	private EHRI_DOI_Metadata_Admin $admin;

	/**
	 * DOI repository.
	 *
	 * @var EHRI_DOI_Repository
	 */
	private EHRI_DOI_Repository $repository;

	/**
	 * DOI metadata helpers.
	 *
	 * @var EHRI_DOI_Metadata_Helpers
	 */
	private EHRI_DOI_Metadata_Helpers $helpers;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Initialize admin panel.
		$this->admin      = new EHRI_DOI_Metadata_Admin();
		$this->repository = new EHRI_DOI_Repository(
			$this->admin->get_service_url(),
			$this->admin->get_client_id(),
			$this->admin->get_client_secret()
		);
		$this->helpers    = new EHRI_DOI_Metadata_Helpers();

		// Register activation hook.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		// Add meta box to post edit screen.
		add_action( 'add_meta_boxes', array( $this, 'add_doi_meta_box' ) );

		// Save post meta.
		add_action( 'save_post', array( $this, 'save_meta' ) );

		// Register scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'register_assets' ) );

		// Add settings link to plugin page.
		add_filter(
			'plugin_action_links_ehri-wordpress-doi-plugin/ehri-doi-metadata-plugin.php',
			array(
				$this,
				'add_settings_link',
			)
		);

		// Register AJAX handlers.
		$this->register_ajax_handlers();
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public function activate(): void {
		// Setup plugin on activation.
		// (create database tables if needed).
	}

	/**
	 * Add settings link to plugin page.
	 *
	 * @param array $links The existing links.
	 */
	public function add_settings_link( array $links ): array {
		// Build and escape the URL.
		$settings_link = '<a href="' .
						admin_url( 'admin.php?page=ehri-doi-metadata-settings' ) . '">' .
						__( 'Settings', 'edmp' ) . '</a>';

		// Add the link to the beginning of the array.
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	private function register_ajax_handlers(): void {
		add_action( 'wp_ajax_open_doi_modal', array( $this, 'ajax_open_doi_modal' ) );
		add_action( 'wp_ajax_register_doi_metadata', array( $this, 'ajax_register_doi_metadata' ) );
		add_action( 'wp_ajax_delete_draft_doi', array( $this, 'ajax_delete_draft_doi' ) );

		add_action(
			'wp_ajax_register_doi',
			function () {
				$this->ajax_update_doi_state( 'register' );
			}
		);
		add_action(
			'wp_ajax_publish_doi',
			function () {
				$this->ajax_update_doi_state( 'publish' );
			}
		);
		add_action(
			'wp_ajax_hide_doi',
			function () {
				$this->ajax_update_doi_state( 'hide' );
			}
		);
	}

	/**
	 * Add the DOI metadata meta box to the post edit screen.
	 *
	 * @return void
	 */
	public function add_doi_meta_box() {
		add_meta_box(
			'doi-metadata-box',
			'DOI Metadata',
			array( $this, 'render_meta_box' ),
			'post',
			'side',
			'default'
		);
	}

	/**
	 * Render the DOI metadata meta box.
	 *
	 * @param WP_Post $post The post object.
	 *
	 * @return void
	 */
	public function render_meta_box( WP_Post $post ) {
		// Get existing metadata if any.
		$doi   = get_post_meta( $post->ID, '_doi', true );
		$state = get_post_meta( $post->ID, '_doi_state', true );

		wp_nonce_field( 'doi_metadata_nonce', 'doi_metadata_nonce' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::get_meta_box_html( $doi, $state );
	}

	/**
	 * Render the HTML for the DOI metadata meta box.
	 *
	 * @param string|null $doi The DOI.
	 * @param string|null $state The DOI state.
	 *
	 * @return false|string
	 */
	private static function get_meta_box_html( string $doi = null, string $state = null ) {
		ob_start();
		?>
		<div class="doi-metadata-panel" id="doi-metadata-info">
			<?php if ( $doi ) : ?>
				<dl>
					<dt>DOI:</dt>
					<dd><?php echo esc_html( $doi ); ?></dd>
					<dt>State:</dt>
					<dd>
						<span class="doi-state-container doi-state-<?php echo esc_attr( $state ); ?>">
							<span class="doi-state-icon"></span>
							<span><?php echo esc_html( ucfirst( $state ) ); ?></span>
						</span>
					</dd>
				</dl>
			<?php else : ?>
				<p>No DOI registered for this post.</p>
			<?php endif; ?>
			<button type="button" id="edit_doi_button" class="button">Manage DOI</button>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Save the DOI metadata for the post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	public function save_meta( int $post_id ) {
		// Verify nonce and user permissions.
		if ( ! isset( $_POST['doi_metadata_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['doi_metadata_nonce'] ) ), 'doi_metadata_nonce' ) ||
			! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save simple DOI field (full metadata saved via AJAX).
		if ( isset( $_POST['doi_field'] ) ) {
			update_post_meta( $post_id, '_doi', sanitize_text_field( wp_unslash( $_POST['doi_field'] ) ) );
		}
	}

	/**
	 * Register assets for the plugin.
	 *
	 * @param string $hook The current admin page hook.
	 *
	 * @return void
	 */
	public function register_assets( string $hook ) {
		// Only load on post edit screen.
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'ehri-doi-url-css',
			plugins_url( 'css/ehri-doi-url.css', EHRI_DOI_PLUGIN_PATH ),
			array(),
			'1.0.0'
		);

		wp_enqueue_style(
			'ehri-doi-metadata-modal-css',
			plugins_url( 'css/ehri-doi-metadata-modal.css', EHRI_DOI_PLUGIN_PATH ),
			array( 'wp-jquery-ui-dialog' ),
			'1.0.0'
		);

		wp_enqueue_script(
			'ehri-doi-metadata-modal-js',
			plugins_url( 'js/ehri-doi-metadata-modal.js', EHRI_DOI_PLUGIN_PATH ),
			array( 'jquery', 'jquery-ui-dialog' ),
			'1.0.0',
			true
		);

		// Pass data to JavaScript.
		wp_localize_script(
			'ehri-doi-metadata-modal-js',
			'doiMetadata',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'doi_metadata_nonce' ),
			)
		);
	}

	/**
	 * Open the modal DOI metadata panel.
	 *
	 * @return void
	 */
	public function ajax_open_doi_modal(): void {
		// Verify nonce and permissions.
		check_ajax_referer( 'doi_metadata_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		// Get all metadata.
		$doi = get_post_meta( $post_id, '_doi', true );
		if ( ! empty( $doi ) ) {
			$this->fetch_doi_metadata( $post_id, $doi );
		} else {
			$data = $this->initialize_doi_metadata( $post_id );
			// Send data for the modal.
			wp_send_json_success(
				array(
					'data'       => $data,
					'modal_html' => $this->get_modal_html( $post_id, $data ),
				)
			);
		}
	}

	/**
	 * Update the DOI state.
	 *
	 * @param string $event The event to trigger.
	 *
	 * @return void
	 */
	public function ajax_update_doi_state( string $event ): void {
		// Verify nonce and permissions.
		check_ajax_referer( 'doi_metadata_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$doi = get_post_meta( $post_id, '_doi', true );
		if ( ! empty( $doi ) ) {

			// Prepare the metadata payload.
			$metadata = array(
				'data' => array(
					'type'       => 'dois',
					'attributes' => array(
						'event' => $event,
					),
				),
				'meta' => array(
					'target' => get_the_permalink( $post_id ),
				),
			);

			try {
				$response_data = $this->repository->update_doi( $doi, $metadata );
				$attrs         = $response_data['data']['attributes'];
				$state         = $attrs['state'];
				$this->save_doi_post_metadata( $post_id, $doi, $state );

				// Calculate changes.
				$changed = EHRI_DOI_Metadata_Helpers::changed_fields( $attrs, $this->initialize_doi_metadata( $post_id ) );

				wp_send_json_success(
					array(
						'doi'        => $doi,
						'modal_html' => $this->get_modal_html( $post_id, $attrs, $doi, $state, $changed ),
						'panel_html' => $this->get_meta_box_html( $doi, $state ?? 'draft' ),
					)
				);
			} catch ( EHRI_DOI_Repository_Exception $e ) {
				wp_send_json_error( 'Error updating DOI' );
			}
		} else {
			wp_send_json_error( 'No DOI found for this post' );
		}
	}

	/**
	 * Get the DOI metadata from the post information and the
	 * plugin settings.
	 *
	 * @param int $post_id the post ID.
	 *
	 * @return void|array
	 */
	private function initialize_doi_metadata( int $post_id ): array {
		$data = array(
			'titles'               => $this->helpers->get_title_info( $post_id ),
			'descriptions'         => $this->helpers->get_description_info( $post_id ),
			'creators'             => $this->helpers->get_author_info( $post_id ),
			'publisher'            => $this->admin->get_options()['publisher'],
			'publicationYear'      => $this->helpers->get_publication_year( $post_id ),
			'dates'                => $this->helpers->get_date_info( $post_id ),
			'alternateIdentifiers' => $this->helpers->get_alternative_identifier_info( $post_id ),
			'formats'              => array( 'text/html' ),
			'types'                => array(
				'resourceType'        => 'Blog Post',
				'resourceTypeGeneral' => 'Text',
			),
			'language'             => $this->helpers->get_language_code( $post_id ),
			'relatedIdentifiers'   => $this->helpers->get_translations( $post_id ),
		);

		// If we have a DOI for this post already, add the URL to the data based
		// on the service resolver URL.
		$doi = get_post_meta( $post_id, '_doi', true );
		if ( $doi ) {
			$data['url'] = $this->admin->get_service_url() . '/' . $doi;
		}

		return $data;
	}

	/**
	 * Fetch DOI metadata from the DataCite API.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $doi The DOI to fetch.
	 *
	 * @return void
	 */
	private function fetch_doi_metadata( int $post_id, string $doi ): void {
		// Fetch metadata from DataCite API.
		try {
			$response_data = $this->repository->get_doi_metadata( $doi );

			// Calculate changes.
			$attrs     = $response_data['data']['attributes'];
			$tombstone = $response_data['meta'] ?? ( $response_data['meta']['tombstone'] ?? false );
			$state     = $attrs['state'] ?? 'draft';
			$init_data = $this->initialize_doi_metadata( $post_id );
			$changed   = $doi ? EHRI_DOI_Metadata_Helpers::changed_fields( $attrs, $init_data ) : array();

			// Send data for the modal.
			wp_send_json_success(
				array(
					'data'       => $attrs,
					'modal_html' => $this->get_modal_html( $post_id, $init_data, $doi, $state, $changed, $tombstone ),
				)
			);
		} catch ( EHRI_DOI_Repository_Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Delete a draft DOI.
	 */
	public function ajax_delete_draft_doi(): void {
		// Verify nonce and permissions.
		check_ajax_referer( 'doi_metadata_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$doi = get_post_meta( $post_id, '_doi', true );
		$this->delete_doi( $post_id, $doi );
	}

	/**
	 * Create or update DOI metadata for the post.
	 *
	 * @return void
	 */
	public function ajax_register_doi_metadata(): void {
		// Verify nonce and permissions.
		check_ajax_referer( 'doi_metadata_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$doi = get_post_meta( $post_id, '_doi', true );
		if ( $doi ) {
			$this->update_doi_metadata( $post_id, $doi );
		} else {
			$this->create_doi( $post_id );
		}
	}

	/**
	 * Delete a draft DOI.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $doi The DOI to delete.
	 *
	 * @return void
	 */
	private function delete_doi( int $post_id, string $doi ) {
		try {
			// Delete the DOI from the DataCite API.
			$this->repository->delete_doi( $doi );

			// Remove the DOI from the post metadata.
			$this->delete_doi_post_metadata( $post_id, $doi );

			wp_send_json_success(
				array(
					'message'    => 'DOI deleted successfully: DOI ' . $doi,
					'doi'        => $doi,
					'panel_html' => $this->get_meta_box_html( '', 'draft' ),
				)
			);
		} catch ( EHRI_DOI_Repository_Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Update the DOI metadata for the post.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $doi The DOI.
	 *
	 * @return void
	 */
	private function update_doi_metadata( int $post_id, string $doi ): void {
		$post_data = $this->initialize_doi_metadata( $post_id );

		// Prepare the metadata payload.
		$metadata = array(
			'data' => array(
				'type'       => 'dois',
				'attributes' => $post_data,
			),
			'meta' => array(
				'target' => get_the_permalink( $post_id ),
			),
		);

		try {
			// Send the metadata to the DataCite API.
			$response_data = $this->repository->update_doi( $doi, $metadata );

			// Save the updated post info.
			$state = $response_data['data']['attributes']['state'];
			$this->save_doi_post_metadata( $post_id, $doi, $state );

			wp_send_json_success(
				array(
					'message'    => 'DOI metadata updated successfully: DOI ' . $doi,
					'doi'        => $doi,
					'modal_html' => $this->get_modal_html( $post_id, $response_data['data']['attributes'], $doi, $state ),
					'panel_html' => $this->get_meta_box_html( $doi, $state ?? 'draft' ),
				)
			);
		} catch ( EHRI_DOI_Repository_Exception $e ) {
			wp_send_json_error( 'Error updating DOI metadata' );
		}
	}

	/**
	 * Register a new DOI for the post.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
	 */
	private function create_doi( int $post_id ): void {

		// Fetch the attributes from the form POST data.
		$init_data = $this->initialize_doi_metadata( $post_id );
		$prefix    = $this->admin->get_options()['prefix'];

		// Prepare the metadata payload.
		$metadata = array(
			'data' => array(
				'type'       => 'dois',
				'attributes' => array(
					'prefix' => $prefix,
				),
			),
			'meta' => array(
				'target' => get_the_permalink( $post_id ),
			),
		);
		// set post data.
		foreach ( $init_data as $key => $value ) {
			$metadata['data']['attributes'][ $key ] = $value;
		}

		try {
			// Send the metadata to the DataCite API.
			$response_data = $this->repository->create_doi( $metadata );

			$doi   = $response_data['data']['id'];
			$attrs = $response_data['data']['attributes'];
			$state = $attrs['state'] ?? 'draft';
			$this->save_doi_post_metadata( $post_id, $doi, $state );

			wp_send_json_success(
				array(
					'message'    => 'DOI metadata registered successfully: DOI ' . $doi,
					'doi'        => $doi,
					'modal_html' => $this->get_modal_html( $post_id, $attrs, $doi, $state ),
					'panel_html' => $this->get_meta_box_html( $doi, $state ),
				)
			);
		} catch ( EHRI_DOI_Repository_Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Display a modal containing the DOI metadata, plus any fields that
	 * require updating to reflect the state of the current post.
	 *
	 * @param int         $post_id the post ID.
	 * @param array       $data the metadata to display.
	 * @param string|null $doi the DOI, if available.
	 * @param string      $state the state of the DOI (draft, registered, findable).
	 * @param array       $changed an array of fields which differ on the DOI metadata
	 *                               and the post metadata.
	 * @param array|false $tombstone whether the DOI is a tombstone.
	 *
	 * @return string the HTML for the modal.
	 */
	private function get_modal_html( int $post_id, array $data, string $doi = null, string $state = 'draft', array $changed = array(), $tombstone = false ): string {
		$renderer = new EHRI_DOI_Metadata_Renderer( $data, $doi, $state, $changed );
		ob_start();
		?>
		<div id="doi-metadata-modal" title="Manage DOI Metadata">
			<form id="doi-metadata-form">
				<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>"/>
				<input type="hidden" name="doi" value="<?php echo esc_attr( $doi ); ?>"/>
				<input type="hidden" name="doi_state" value="<?php echo esc_attr( $state ); ?>"/>

				<?php if ( $tombstone ) : ?>
					<p class="doi-tombstone-warning">
						<strong><?php esc_html_e( 'Item marked as deleted', 'edmp' ); ?></strong>
					</p>
				<?php endif; ?>

				<?php echo $renderer->render_doi_metadata(); ?>

				<div class="button-row">
					<?php if ( $doi ) : ?>
						<button type="button" id="register-doi-metadata"
								class="button button-primary" <?php echo empty( $changed ) ? 'disabled' : ''; ?>>
							Update DOI Metadata
						</button>

						<?php if ( 'draft' === $state ) : ?>
							<button type="button" id="register-doi" class="button button-secondary"
									title="Change draft DOI state to 'registered'. Registered DOIs can no longer be deleted.">
								Publish DOI
							</button>
							<button type="button" id="delete-doi" class="button-link button-link-delete"
									title="Permanently delete draft DOI">Delete DOI
							</button>
						<?php endif; ?>

						<?php if ( 'registered' === $state ) : ?>
							<button type="button" id="publish-doi" class="button button-secondary"
									title="Allow DOI to be discoverable via search">Make DOI Findable
							</button>
						<?php elseif ( 'findable' === $state ) : ?>
							<button type="button" id="hide-doi" class="button button-secondary">Hide DOI</button>
						<?php endif; ?>
					<?php else : ?>
						<button type="button" id="register-doi-metadata" class="button button-primary">Create Draft
							DOI
						</button>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Save the DOI and its state to the post metadata.
	 *
	 * @param int    $post_id the post ID.
	 * @param string $doi the DOI.
	 * @param string $state the state of the DOI (draft, registered, findable).
	 *
	 * @return void
	 */
	private function save_doi_post_metadata( int $post_id, string $doi, string $state = 'draft' ): void {
		// Save the DOI to the post meta.
		update_post_meta( $post_id, '_doi', $doi );
		update_post_meta( $post_id, '_doi_state', $state );
	}

	/**
	 * Delete the DOI metadata from the post.
	 *
	 * @param int    $post_id the post ID.
	 * @param string $doi the DOI.
	 */
	private function delete_doi_post_metadata( int $post_id, string $doi ): void {
		// Delete the DOI from the post meta.
		delete_post_meta( $post_id, '_doi', $doi );
		delete_post_meta( $post_id, '_doi_state' );
	}
}
