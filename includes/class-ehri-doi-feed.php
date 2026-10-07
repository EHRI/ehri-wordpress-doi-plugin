<?php
/**
 * EHRI DOI Feed
 *
 * @package ehri-pid-tools
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds post DOIs to the RSS2 and Atom feeds as `prism:doi` elements.
 */
class EHRI_DOI_Feed {
	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rss2_ns', array( $this, 'add_prism_namespace' ) );
		add_action( 'rss2_item', array( $this, 'render_doi' ) );
		add_action( 'atom_ns', array( $this, 'add_prism_namespace' ) );
		add_action( 'atom_entry', array( $this, 'render_doi' ) );
	}

	/**
	 * Declare the PRISM namespace.
	 *
	 * @return void
	 */
	public function add_prism_namespace(): void {
		echo ' xmlns:prism="http://prismstandard.org/namespaces/basic/2.0/"' . "\n";
	}

	/**
	 * Output the current feed item's DOI, if it has a public one.
	 *
	 * @return void
	 */
	public function render_doi(): void {
		$post_id = get_the_ID();
		$doi     = get_post_meta( $post_id, EHRI_DOI_META_KEY, true );
		$state   = get_post_meta( $post_id, EHRI_DOI_STATE_META_KEY, true );
		if ( ! $doi || ! EHRI_DOI_Metadata_Helpers::is_doi_public( (string) $state ) ) {
			return;
		}

		printf( "\t\t<prism:doi>%s</prism:doi>\n", esc_html( $doi ) );
	}
}
