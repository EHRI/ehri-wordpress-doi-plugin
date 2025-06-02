<?php
/**
 * Plugin Name: EHRI DOI Metadata Plugin
 * Description: Adds DOI metadata management capabilities to WordPress posts
 * Version: 1.0.0
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

// Include the metadata plugin class.
require_once EHRI_DOI_PLUGIN_DIR . 'includes/class-ehri-doi-metadata-manager.php';

// Include the version manager class.
require_once EHRI_DOI_PLUGIN_DIR . 'includes/class-ehri-doi-version-manager.php';


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
