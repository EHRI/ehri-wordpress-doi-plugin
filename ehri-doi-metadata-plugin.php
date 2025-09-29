<?php
/**
 * Plugin Name: EHRI DOI Metadata Plugin
 * Description: Adds DOI metadata management capabilities to WordPress posts
 * Version: 1.0.0
 * Text Domain: edmp
 * Domain Path: /languages
 *
 * @package ehri-pid-tools
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EHRI_DOI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EHRI_DOI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EHRI_DOI_PLUGIN_PATH', __FILE__ );

/**
 * These keys are used to store the DOI, its state, and the previous version of a post.
 */
const EHRI_DOI_META_KEY                  = '_doi';
const EHRI_DOI_STATE_META_KEY            = '_doi_state';
const EHRI_DOI_PREVIOUS_VERSION_META_KEY = '_previous_version_of';
const EHRI_DOI_PLUGIN_OPTION_PREFIX      = '_ehri_doi_plugin_options';
const EHRI_DOI_PLUGIN_DEBUG              = false;

// Include the metadata plugin class.
require_once EHRI_DOI_PLUGIN_DIR . 'includes/class-ehri-doi-metadata-manager.php';

// Include the version manager class.
require_once EHRI_DOI_PLUGIN_DIR . 'includes/class-ehri-doi-version-manager.php';

// Include the events system.
require_once EHRI_DOI_PLUGIN_DIR . 'includes/class-ehri-doi-events.php';

// Include citation widget.
require_once EHRI_DOI_PLUGIN_DIR . 'includes/class-ehri-doi-citation-widget.php';

// Include DOI URL widget.
require_once EHRI_DOI_PLUGIN_DIR . 'includes/class-ehri-doi-url-widget.php';

// If in DEBUG mode, print the events on the log.
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log
if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || EHRI_DOI_PLUGIN_DEBUG ) {
	add_action(
		'ehri_doi_created',
		function ( $doi, $post_id, $metadata, $state ) {
			error_log( sprintf( 'DOI Created: %s for post %d with state %s', $doi, $post_id, $state ) );
		},
		10,
		4
	);

	add_action(
		'ehri_doi_updated',
		function ( $doi, $post_id, $old_metadata, $new_metadata, $changed_fields ) {
			error_log( sprintf( 'DOI Updated: %s for post %d. Changed fields: %s', $doi, $post_id, implode( ', ', $changed_fields ) ) );
		},
		10,
		5
	);

	add_action(
		'ehri_doi_deleted',
		function ( $doi, $post_id, $metadata ) {
			error_log( sprintf( 'DOI Deleted: %s for post %d', $doi, $post_id ) );
		},
		10,
		3
	);

	add_action(
		'ehri_doi_state_changed',
		function ( $doi, $post_id, $old_state, $new_state, $event ) {
			error_log( sprintf( 'DOI State Changed (%s): %s for post %d from %s to %s', $event, $doi, $post_id, $old_state, $new_state ) );
		},
		10,
		5
	);

	add_action(
		'ehri_post_version_set',
		function ( $post_id, $other_post_id ) {
			error_log( sprintf( 'DOI Version Created: Post %d set as previous version of post %d', $post_id, $other_post_id ) );
		},
		10,
		2
	);

	add_action(
		'ehri_post_version_removed',
		function ( $post_id, $other_post_id ) {
			error_log( sprintf( 'DOI Version Removed: Post %d no longer a previous version of post %d', $post_id, $other_post_id ) );
		},
		10,
		2
	);
}
// phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log

/**
 * Activate the EHRI DOI Metadata & Version Plugins.
 */
function ehri_doi_activate_plugins() {
	$doi_metadata_manager = new EHRI_DOI_Metadata_Manager();
	$doi_metadata_manager->activate();

	$doi_version_manager = new EHRI_DOI_Version_Manager();
	$doi_version_manager->activate();
}

ehri_doi_activate_plugins();
