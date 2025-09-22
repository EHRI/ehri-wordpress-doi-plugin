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
		$this->helpers    = new EHRI_DOI_Metadata_Helpers( $this->admin );

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
			__( 'DOI Metadata', 'edmp' ),
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
		$doi   = get_post_meta( $post->ID, EHRI_DOI_META_KEY, true );
		$state = get_post_meta( $post->ID, EHRI_DOI_STATE_META_KEY, true );

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
			update_post_meta( $post_id, EHRI_DOI_META_KEY, sanitize_text_field( wp_unslash( $_POST['doi_field'] ) ) );
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
				'strings' => array(
					'errorOpeningModal'   => __( 'An error occurred while trying to open the DOI metadata editor.', 'edmp' ),
					'errorSavingMetadata' => __( 'An error occurred while saving the DOI metadata.', 'edmp' ),
					'errorDeletingDoi'    => __( 'An error occurred while deleting the DOI metadata.', 'edmp' ),
					'errorUpdatingState'  => __( 'An error occurred while hiding the DOI.', 'edmp' ),
					'confirmDeleteDoi'    => __( 'Are you sure you want to delete this draft DOI?', 'edmp' ),
					'error'               => __( 'Error: ', 'edmp' ),
					'close'               => __( 'Close', 'edmp' ),
				),
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
		$doi = get_post_meta( $post_id, EHRI_DOI_META_KEY, true );
		if ( ! empty( $doi ) ) {
			$this->fetch_doi_metadata( $post_id, $doi );
		} else {
			$post_attributes = $this->initialize_doi_metadata( $post_id );
			// Send data for the modal.
			wp_send_json_success(
				array(
					'data'       => $post_attributes,
					'modal_html' => $this->get_modal_html( $post_id, $post_attributes ),
				)
			);
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
			'publisher'            => $this->helpers->get_publisher(),
			'publicationYear'      => $this->helpers->get_publication_year( $post_id ),
			'dates'                => $this->helpers->get_date_info( $post_id ),
			'alternateIdentifiers' => $this->helpers->get_alternative_identifier_info( $post_id ),
			'formats'              => array( 'text/html' ),
			'subjects'             => $this->helpers->get_subject_info( $post_id ),
			'types'                => array(
				'ris'                 => 'BLOG',
				'citeproc'            => 'webpage',
				'bibtex'              => 'misc',
				'schemaOrg'           => 'BlogPosting',
				'resourceType'        => 'Blog Post',
				'resourceTypeGeneral' => 'Text',
			),
			'language'             => $this->helpers->get_language_code( $post_id ),
			'relatedIdentifiers'   => $this->helpers->get_related_identifiers( $post_id ),
			'relatedItems'         => $this->helpers->get_related_items( $post_id ),
			'version'              => $this->helpers->get_version_info( $post_id ),
		);

		// If we have a DOI for this post already, add the URL to the data based
		// on the service resolver URL.
		$doi = get_post_meta( $post_id, EHRI_DOI_META_KEY, true );
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
		// Fetch post attributes.
		$post_attributes = $this->initialize_doi_metadata( $post_id );

		// Fetch metadata from DataCite API.
		try {
			$doi_data = $this->repository->get_doi_metadata( $doi );

			// Calculate changes.
			$doi_attributes = $doi_data['data']['attributes'];
			$doi_tombstone  = $doi_data['meta']['tombstone'] ?? false;
			$doi_state      = $doi_attributes['state'] ?? 'draft';
			$changed_fields = $doi ? EHRI_DOI_Metadata_Helpers::changed_fields( $doi_attributes, $post_attributes ) : array();

			// Send data for the modal.
			wp_send_json_success(
				array(
					'data'       => $doi_attributes,
					'modal_html' => $this->get_modal_html( $post_id, $post_attributes, $doi, $doi_state, $changed_fields, $doi_tombstone ),
				)
			);
		} catch ( EHRI_DOI_Repository_Exception $e ) {
			// Fire error event.
			EHRI_DOI_Events::doi_api_error( 'get', $doi, $post_id, $e->getMessage(), $e->getCode() );
			wp_send_json_error( $e->getMessage() . ' ' . $e->getCode() );
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

		$doi = get_post_meta( $post_id, EHRI_DOI_META_KEY, true );
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

		$doi = get_post_meta( $post_id, EHRI_DOI_META_KEY, true );
		if ( $doi ) {
			$this->update_doi_metadata( $post_id, $doi );
		} else {
			$this->create_doi( $post_id );
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
		$post_attributes = $this->initialize_doi_metadata( $post_id );
		$prefix          = $this->admin->get_options()['prefix'];

		// Fire before operation event.
		EHRI_DOI_Events::before_doi_operation( 'create', '', $post_id, array( 'metadata' => $post_attributes ) );

		// Prepare the metadata payload.
		$payload = array(
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
		foreach ( $post_attributes as $key => $value ) {
			$payload['data']['attributes'][ $key ] = $value;
		}

		try {
			// Send the metadata to the DataCite API.
			$doi_data = $this->repository->create_doi( $payload );

			$doi            = $doi_data['data']['id'];
			$doi_attributes = $doi_data['data']['attributes'];
			$doi_tombstone  = $doi_data['meta']['tombstone'] ?? false;
			$doi_state      = $doi_attributes['state'] ?? 'draft';
			$this->save_doi_post_metadata( $post_id, $doi, $doi_state );
			// Because we just created the DOI, there are no old metadata to compare against.
			// However, if we get changes here it will show a bug in the plugin.
			$changed_fields = EHRI_DOI_Metadata_Helpers::changed_fields( $doi_attributes, $post_attributes );

			// Fire success events.
			EHRI_DOI_Events::doi_created( $doi, $post_id, $doi_attributes, $doi_state );
			EHRI_DOI_Events::after_doi_operation( 'create', $doi, $post_id, true, $doi_attributes );

			wp_send_json_success(
				array(
					// translators: %s is the DOI identifier.
					'message'    => sprintf( __( 'DOI metadata registered successfully: DOI %s', 'edmp' ), $doi ),
					'doi'        => $doi,
					'modal_html' => $this->get_modal_html( $post_id, $post_attributes, $doi, $doi_state, $changed_fields, $doi_tombstone ),
					'panel_html' => $this->get_meta_box_html( $doi, $doi_state ),
				)
			);
		} catch ( EHRI_DOI_Repository_Exception $e ) {
			// Fire error events.
			EHRI_DOI_Events::doi_api_error( 'create', '', $post_id, $e->getMessage(), $e->getCode() );
			EHRI_DOI_Events::after_doi_operation( 'create', '', $post_id, false, array( 'error' => $e->getMessage() ) );

			wp_send_json_error( $e->getMessage() . ' ' . $e->getCode() );
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
		$post_attributes = $this->initialize_doi_metadata( $post_id );

		// Fire before operation event.
		EHRI_DOI_Events::before_doi_operation( 'update', $doi, $post_id, array( 'metadata' => $post_attributes ) );

		// Get existing metadata for comparison.
		try {
			$existing_data  = $this->repository->get_doi_metadata( $doi );
			$old_attributes = $existing_data['data']['attributes'] ?? array();
		} catch ( EHRI_DOI_Repository_Exception $e ) {
			// Continue with update even if we can't get old metadata.
			EHRI_DOI_Events::doi_api_error( 'get', $doi, $post_id, $e->getMessage(), $e->getCode() );
			$old_attributes = array();
		}

		// Prepare the metadata payload.
		$payload = array(
			'data' => array(
				'type'       => 'dois',
				'attributes' => $post_attributes,
			),
			'meta' => array(
				'target' => get_the_permalink( $post_id ),
			),
		);

		try {
			// Send the metadata to the DataCite API.
			$doi_data = $this->repository->update_doi( $doi, $payload );

			// Save the updated post info.
			$doi_attributes = $doi_data['data']['attributes'];
			$doi_tombstone  = $doi_data['meta']['tombstone'] ?? false;
			$doi_state      = $doi_attributes['state'];
			$this->save_doi_post_metadata( $post_id, $doi, $doi_state );

			// Calculate changed fields and fire events.
			$changed_fields = EHRI_DOI_Metadata_Helpers::changed_fields( $old_attributes, $post_attributes );
			EHRI_DOI_Events::doi_updated( $doi, $post_id, $old_attributes, $doi_attributes, $changed_fields );
			EHRI_DOI_Events::after_doi_operation( 'update', $doi, $post_id, true, $doi_attributes );

			// Recalculate the changed fields with the updated data (there should be no changes, unless there's a bug).
			$changed_fields = EHRI_DOI_Metadata_Helpers::changed_fields( $doi_attributes, $post_attributes );

			wp_send_json_success(
				array(
					// translators: %s is the DOI identifier.
					'message'    => sprintf( __( 'DOI metadata updated successfully for DOI: %s', 'edmp' ), $doi ),
					'doi'        => $doi,
					'modal_html' => $this->get_modal_html( $post_id, $post_attributes, $doi, $doi_state, $changed_fields, $doi_tombstone ),
					'panel_html' => $this->get_meta_box_html( $doi, $doi_state ?? 'draft' ),
				)
			);
		} catch ( EHRI_DOI_Repository_Exception $e ) {
			// Fire error events.
			EHRI_DOI_Events::doi_api_error( 'update', $doi, $post_id, $e->getMessage(), $e->getCode() );
			EHRI_DOI_Events::after_doi_operation( 'update', $doi, $post_id, false, array( 'error' => $e->getMessage() ) );

			wp_send_json_error( 'Error updating DOI metadata' );
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
		// Get existing metadata before deletion.
		try {
			$existing_data  = $this->repository->get_doi_metadata( $doi );
			$old_attributes = $existing_data['data']['attributes'] ?? array();
		} catch ( EHRI_DOI_Repository_Exception $e ) {
			// Continue with deletion even if we can't get metadata.
			EHRI_DOI_Events::doi_api_error( 'get', $doi, $post_id, $e->getMessage(), $e->getCode() );
			$old_attributes = array();
		}

		// Fire before operation event.
		EHRI_DOI_Events::before_doi_operation( 'delete', $doi, $post_id, array( 'metadata' => $old_attributes ) );

		try {
			// Delete the DOI from the DataCite API.
			$this->repository->delete_doi( $doi );

			// Remove the DOI from the post metadata.
			$this->delete_doi_post_metadata( $post_id, $doi );

			// Fire success events.
			EHRI_DOI_Events::doi_deleted( $doi, $post_id, $old_attributes );
			EHRI_DOI_Events::after_doi_operation( 'delete', $doi, $post_id, true, array( 'deleted_metadata' => $old_attributes ) );

			$post_attributes = $this->initialize_doi_metadata( $post_id );
			wp_send_json_success(
				array(
					// translators: %s is the DOI identifier.
					'message'    => sprintf( __( 'DOI deleted successfully: DOI %s', 'edmp' ), $doi ),
					'doi'        => $doi,
					'modal_html' => $this->get_modal_html( $post_id, $post_attributes ),
					'panel_html' => $this->get_meta_box_html( '', 'draft' ),
				)
			);
		} catch ( EHRI_DOI_Repository_Exception $e ) {
			// Fire error events.
			EHRI_DOI_Events::doi_api_error( 'delete', $doi, $post_id, $e->getMessage(), $e->getCode() );
			EHRI_DOI_Events::after_doi_operation( 'delete', $doi, $post_id, false, array( 'error' => $e->getMessage() ) );

			wp_send_json_error( $e->getMessage() );
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

		$doi = get_post_meta( $post_id, EHRI_DOI_META_KEY, true );
		if ( ! empty( $doi ) ) {

			// Get current state for comparison.
			$old_state = get_post_meta( $post_id, EHRI_DOI_STATE_META_KEY, true ) ?? 'draft';

			// Fire before operation event.
			EHRI_DOI_Events::before_doi_operation(
				'state_change',
				$doi,
				$post_id,
				array(
					'event'     => $event,
					'old_state' => $old_state,
				)
			);

			// Prepare the metadata payload.
			$payload = array(
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
				$doi_data       = $this->repository->update_doi( $doi, $payload );
				$doi_attributes = $doi_data['data']['attributes'];
				$doi_tombstone  = $doi_data['meta']['tombstone'] ?? false;
				$doi_state      = $doi_attributes['state'];
				$this->save_doi_post_metadata( $post_id, $doi, $doi_state );

				// Fire state change and success events.
				if ( $old_state !== $doi_state ) {
					EHRI_DOI_Events::doi_state_changed( $doi, $post_id, $old_state, $doi_state, $event );
				}
				EHRI_DOI_Events::after_doi_operation(
					'state_change',
					$doi,
					$post_id,
					true,
					array(
						'new_state' => $doi_state,
						'event'     => $event,
					)
				);

				// Calculate changes.
				$post_attributes = $this->initialize_doi_metadata( $post_id );
				$changed_fields  = EHRI_DOI_Metadata_Helpers::changed_fields( $doi_attributes, $post_attributes );

				wp_send_json_success(
					array(
						'message'    => sprintf(
							// translators: %s is the DOI identifier.
							__( 'DOI state updated successfully: %s', 'edmp' ),
							$doi
						),
						'doi'        => $doi,
						'modal_html' => $this->get_modal_html( $post_id, $post_attributes, $doi, $doi_state, $changed_fields, $doi_tombstone ),
						'panel_html' => $this->get_meta_box_html( $doi, $doi_state ?? 'draft' ),
					)
				);
			} catch ( EHRI_DOI_Repository_Exception $e ) {
				// Fire error event.
				EHRI_DOI_Events::doi_api_error( 'update', $doi, $post_id, $e->getMessage(), $e->getCode() );
				wp_send_json_error( 'Error updating DOI' );
			}
		} else {
			wp_send_json_error( 'No DOI found for this post' );
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
	 *                                     and the post metadata.
	 * @param array|false $tombstone whether the DOI is a tombstone.
	 *
	 * @return string the HTML for the modal.
	 */
	private function get_modal_html( int $post_id, array $data, string $doi = null, string $state = 'draft', array $changed = array(), $tombstone = false ): string {
		$renderer = new EHRI_DOI_Metadata_Renderer( $data, $doi, $state, $changed );
		ob_start();
		?>
		<div id="doi-metadata-modal" title="<?php esc_attr_e( 'Manage DOI Metadata', 'edmp' ); ?>">
			<form id="doi-metadata-form">
				<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>"/>
				<input type="hidden" name="doi" value="<?php echo esc_attr( $doi ); ?>"/>
				<input type="hidden" name="doi_state" value="<?php echo esc_attr( $state ); ?>"/>

				<?php if ( $tombstone ) : ?>
					<p class="doi-tombstone-warning">
						<strong><?php esc_html_e( 'Item marked as deleted', 'edmp' ); ?></strong>
						<?php esc_html_e( 'since ', 'edmp' ); ?>
						<?php echo esc_html( EHRI_DOI_Metadata_Helpers::format_iso_date( $tombstone['deletedAt'] ) ); ?>
					</p>
				<?php endif; ?>

				<?php echo $renderer->render_doi_metadata(); ?>

				<div class="button-row">
					<?php if ( $doi ) : ?>
						<button type="button" id="register-doi-metadata"
								class="button button-primary" <?php echo empty( $changed ) ? 'disabled' : ''; ?>>
							<?php esc_html_e( 'Update DOI Metadata', 'edmp' ); ?>
						</button>

						<?php if ( 'draft' === $state ) : ?>
							<button type="button" id="register-doi" class="button button-secondary"
									title="<?php esc_attr_e( "Change draft DOI state to 'registered'. Registered DOIs can no longer be deleted.", 'edmp' ); ?>">
								<?php esc_html_e( 'Publish DOI', 'edmp' ); ?>
							</button>
							<button type="button" id="delete-doi" class="button-link button-link-delete"
									title="<?php esc_attr_e( 'Permanently delete draft DOI', 'edmp' ); ?>"><?php esc_html_e( 'Delete DOI', 'edmp' ); ?>
							</button>
						<?php endif; ?>

						<?php if ( 'registered' === $state ) : ?>
							<button type="button" id="publish-doi" class="button button-secondary"
									title="<?php esc_attr_e( 'Allow DOI to be discoverable via search', 'edmp' ); ?>"><?php esc_html_e( 'Make DOI Findable', 'edmp' ); ?>
							</button>
						<?php elseif ( 'findable' === $state ) : ?>
							<button type="button" id="hide-doi" class="button button-secondary"><?php esc_html_e( 'Hide DOI', 'edmp' ); ?></button>
						<?php endif; ?>
					<?php else : ?>
						<button type="button" id="register-doi-metadata" class="button button-primary"><?php esc_html_e( 'Create Draft DOI', 'edmp' ); ?>
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
		update_post_meta( $post_id, EHRI_DOI_META_KEY, $doi );
		update_post_meta( $post_id, EHRI_DOI_STATE_META_KEY, $state );
	}

	/**
	 * Delete the DOI metadata from the post.
	 *
	 * @param int    $post_id the post ID.
	 * @param string $doi the DOI.
	 */
	private function delete_doi_post_metadata( int $post_id, string $doi ): void {
		// Delete the DOI from the post meta.
		delete_post_meta( $post_id, EHRI_DOI_META_KEY, $doi );
		delete_post_meta( $post_id, EHRI_DOI_STATE_META_KEY );
	}
}
