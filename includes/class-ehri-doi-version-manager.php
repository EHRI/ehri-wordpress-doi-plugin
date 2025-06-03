<?php
/**
 * DOI Version Manager
 *
 * @package ehri-pid-tools
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * EHRI DOI Version manager.
 *
 * This class provides functionality to managing setting a post as a new version of an existing post,
 * which will then be registered as a new DOI.
 */
class EHRI_DOI_Version_Manager {
	/**
	 * Meta key for the previous version of a post.
	 */
	public const META_PREVIOUS_VERSION_OF = '_previous_version_of';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register activation hook.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		// Add meta box to post edit screen.
		add_action( 'add_meta_boxes', array( $this, 'add_doi_version_meta_box' ) );

		// Register scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'register_assets' ) );

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
	 * Register AJAX handlers.
	 *
	 * @return void
	 */
	private function register_ajax_handlers(): void {
		add_action( 'wp_ajax_open_doi_version_modal', array( $this, 'ajax_open_doi_version_modal' ) );
		add_action( 'wp_ajax_save_post_replaced_version_id', array( $this, 'ajax_save_post_replaced_version_id' ) );
	}

	/**
	 * Add the DOI version metadata meta box to the post edit screen.
	 *
	 * @return void
	 */
	public function add_doi_version_meta_box() {
		add_meta_box(
			'doi-version-box',
			__( 'DOI Versioning', 'edmp' ),
			array( $this, 'render_meta_box' ),
			'post',
			'side',
			'default'
		);
	}

	/**
	 * Render the DOI version metadata meta box.
	 *
	 * @param WP_Post $post The post object.
	 *
	 * @return void
	 */
	public function render_meta_box( WP_Post $post ) {
		wp_nonce_field( 'doi_version_nonce', 'doi_version_nonce' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo self::get_meta_box_html( $post );
	}

	/**
	 * Render the HTML for the DOI version metadata box. This allows the user to mark the post as replaced
	 * by a new version, which will then be registered as a new DOI.
	 *
	 * @param WP_Post $post The post object.
	 *
	 * @return false|string
	 */
	private static function get_meta_box_html( WP_Post $post ) {
		ob_start();
		$new_version_id = get_post_meta( $post->ID, self::META_PREVIOUS_VERSION_OF, true );
		?>
		<div class="doi-version-panel" id="doi-version-info">
			<?php if ( $new_version_id ) : ?>
				<p>
					<?php esc_html_e( 'This post is marked as replaced by post ID:', 'edmp' ); ?>
					<strong>
						<a target="_blank" title="<?php echo esc_attr( get_the_title( $new_version_id ) ); ?>" href="<?php echo esc_url( get_permalink( $new_version_id ) ); ?>"><?php echo esc_html( $new_version_id ); ?></a>
					</strong>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'This post is not marked as replaced by any other post.', 'edmp' ); ?></p>
			<?php endif; ?>
			<button type="button" id="edit_doi_version_button" class="button">
				<?php esc_html_e( 'Edit', 'edmp' ); ?>
			</button>
		</div>
		<?php
		return ob_get_clean();
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

		wp_enqueue_script(
			'ehri-doi-version-modal-js',
			plugins_url( 'js/ehri-doi-version-modal.js', EHRI_DOI_PLUGIN_PATH ),
			array( 'jquery', 'jquery-ui-dialog' ),
			'1.0.0',
			true
		);

		// Pass data to JavaScript.
		wp_localize_script(
			'ehri-doi-version-modal-js',
			'doiVersion',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'doi_version_nonce' ),
				'strings' => array(
					'errorOpeningModal' => __( 'An error occurred while trying to open the version modal.', 'edmp' ),
					'errorSaving'       => __( 'An error occurred while saving the version information.', 'edmp' ),
					'close'             => __( 'Close', 'edmp' ),
				),
			)
		);
	}

	/**
	 * Open the modal DOI version panel.
	 *
	 * @return void
	 */
	public function ajax_open_doi_version_modal(): void {
		// Verify nonce and permissions.
		check_ajax_referer( 'doi_version_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		// Get all metadata.
		$new_version_id = intval( get_post_meta( $post_id, self::META_PREVIOUS_VERSION_OF, true ) );
		$query_args     = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			// Prevent PolyLang from filtering the query to only give us posts in the current language.
			'lang'           => '',
		);
		$query          = new WP_Query( $query_args );
		$post_info      = array();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				// Skip the current post.
				if ( get_the_ID() === $post_id ) {
					continue;
				}
				$post_info[] = array(
					'ID'    => get_the_ID(),
					'title' => get_the_title(),
				);
			}
			wp_reset_postdata();
		}

		// Send data for the modal.
		wp_send_json_success(
			array(
				'modal_html' => $this->get_modal_html( $post_id, $new_version_id, $post_info ),
			)
		);
	}

	/**
	 * Save the replaced version ID for the post.
	 */
	public function ajax_save_post_replaced_version_id(): void {
		// Verify nonce and permissions.
		check_ajax_referer( 'doi_version_nonce', 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$new_version_id = isset( $_POST['new_version_id'] ) ? intval( $_POST['new_version_id'] ) : 0;
		if ( $new_version_id && $new_version_id > 0 ) {
			update_post_meta( $post_id, self::META_PREVIOUS_VERSION_OF, $new_version_id );
			EHRI_DOI_Events::post_version_set( $post_id, $new_version_id );
			wp_send_json_success(
				array(
					'panel_html' => self::get_meta_box_html( get_post( $post_id ) ),
					// translators: %s is the post ID.
					'message'    => esc_js( sprintf( __( 'Post marked as replaced by post ID: %s', 'edmp' ), $new_version_id ) ),
				)
			);
		} else {
			// If the version is 0 or not set, remove the meta key.
			$existing_post_id = intval( get_post_meta( $post_id, self::META_PREVIOUS_VERSION_OF, true ) );
			delete_post_meta( $post_id, self::META_PREVIOUS_VERSION_OF );
			EHRI_DOI_Events::post_version_removed( $post_id, $existing_post_id );
			wp_send_json_success(
				array(
					'panel_html' => self::get_meta_box_html( get_post( $post_id ) ),
					'message'    => esc_js( __( 'Post no longer marked as replaced.', 'edmp' ) ),
				)
			);
		}
	}

	/**
	 * Display a modal allowing the user to select a post which
	 * replaces this one.
	 *
	 * @param int      $post_id the post ID.
	 * @param int|null $new_version_id the new version ID, if any.
	 * @param array    $post_info the id and title of other posts for selection.
	 *
	 * @return string the HTML for the modal.
	 */
	private function get_modal_html( int $post_id, ?int $new_version_id, array $post_info ): string {
		ob_start();
		?>
		<div id="doi-version-modal" title="<?php esc_attr_e( 'Mark post as replaced', 'edmp' ); ?>">
		<?php if ( ! empty( $post_info ) ) : ?>
			<label for="new_version_select">
				<?php esc_html_e( 'Replacement post:', 'edmp' ); ?>
			</label>
			<select id="new_version_select">
				<option value="0">---</option>
				<?php foreach ( $post_info as $p ) : ?>
					<option value="<?php echo esc_attr( $p['ID'] ); ?>" <?php selected( $p['ID'], $new_version_id ); ?>>
						<?php echo esc_html( wp_strip_all_tags( $p['title'] ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		<?php else : ?>
			<p><?php esc_html_e( 'No other posts available to mark as replaced.', 'edmp' ); ?></p>
		<?php endif; ?>
			<button id="save_doi_version_button" class="button"><?php esc_html_e( 'Save', 'edmp' ); ?></button>
		</div>
		<?php
		return ob_get_clean();
	}
}
