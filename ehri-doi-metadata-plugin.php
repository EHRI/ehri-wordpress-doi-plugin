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

// Include the plugin class.
require_once EHRI_DOI_PLUGIN_DIR . 'includes/class-ehri-doi-metadata-manager.php';

/**
 * Activate the EHRI DOI Metadata Plugin.
 *
 * @return void
 */
function ehri_doi_metadata_plugin_activate() {
	$doi_metadata_manager = new EHRI_DOI_Metadata_Manager();
	$doi_metadata_manager->activate();
}

ehri_doi_metadata_plugin_activate();
