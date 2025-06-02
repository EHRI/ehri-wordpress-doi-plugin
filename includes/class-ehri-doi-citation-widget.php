<?php
/**
 * Plugin Name: Post Citation Widget
 * Description: Displays citation options for posts with DOIs
 * Version: 1.0
 *
 * @package ehri-pid-tools
 */

if ( ! function_exists( 'register_citation_widget' ) ) {
	/**
	 * Register the EHRI_DOI_Citation_Widget widget
	 */
	function register_citation_widget() {
		register_widget( 'EHRI_DOI_Citation_Widget' );
	}
}

add_action( 'widgets_init', 'register_citation_widget' );

/**
 * Enqueue the necessary JavaScript and CSS files
 */
function enqueue_citation_js() {
	wp_enqueue_style( 'ehri-doi-citation-widget-css', plugins_url( 'css/ehri-doi-citation-widget.css', EHRI_DOI_PLUGIN_PATH ), array(), '1.0.0' );
	wp_enqueue_script( 'citation-js', 'https://cdn.jsdelivr.net/npm/citation-js@0.7.18/build/citation.min.js', array(), '0.7.18', true );
	wp_enqueue_script( 'ehri-doi-citation-widget-js', plugin_dir_url( EHRI_DOI_PLUGIN_PATH ) . 'js/ehri-doi-citation-widget.js', array( 'jquery', 'citation-js' ), '1.0', true );
}

add_action( 'wp_enqueue_scripts', 'enqueue_citation_js' );

/**
 * Renders a DOI URL if the post has a DOI and the state is not 'draft'
 */
class EHRI_DOI_Citation_Widget extends WP_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->fake_citation_doi = get_option(
			'_ehri_doi_plugin_options',
			array(
				'fake_citation_doi' => false,
			)
		)['fake_citation_doi'];

		parent::__construct(
			'EHRI_DOI_Citation_Widget',
			__( 'Citation Widget [EHRI]', 'edmp' ),
			array( 'description' => __( 'Displays citation options for posts with DOIs' ) )
		);
	}

	/**
	 * Renders the widget.
	 *
	 * @param array $args Widget arguments.
	 * @param array $instance Widget instance.
	 */
	public function widget( $args, $instance ) {
		global $post;

		// Only show on single posts.
		if ( ! is_single() ) {
			return;
		}

		// Check if the post has a DOI.
		$doi   = get_post_meta( $post->ID, '_doi', true );
		$state = get_post_meta( $post->ID, '_doi_state', true );
		if ( empty( $doi ) || empty( $state ) || 'draft' === $state ) {
			echo '<!-- No DOI or DOI is in draft state, not displaying citation widget -->';
			return;
		}

		// Override the DOI with the fake citation DOI if set.
		if ( $this->fake_citation_doi ) {
			echo '<!-- Using fake citation DOI for testing -->';
			$doi = $this->fake_citation_doi;
		}

		echo $args['before_widget'];
		echo $args['before_title']
			. sprintf(
				'<a href="#" id="show-citation-dialog">%s</a>',
				esc_html__( 'Cite this item', 'edmp' )
			)
			. $args['after_title'];
		?>
		<dialog id="ehri-doi-citation-widget" class="citation-dialog">
			<div class="citation-container">
				<div class="citation-loading"><?php esc_html_e( 'Loading citation data...', 'edmp' ); ?></div>
				<div class="citation-formats" style="display:none;">
					<div class="citation-controls">
						<label for="citation-format-selector"><?php esc_html_e( 'Format:', 'edmp' ); ?></label>
						<select id="citation-format-selector" class="form-control form-control-sm">
							<option value="apa">APA</option>
							<option value="mla">MLA</option>
							<option value="chicago">Chicago</option>
							<option value="harvard">Harvard</option>
							<option value="bibtex">BibTeX</option>
							<option value="ris">RIS</option>
						</select>
						<button id="copy-citation" class="btn btn-xs btn-primary">
							<i class="fa fa-copy"></i>
							<?php esc_html_e( 'Copy', 'edmp' ); ?>
						</button>
					</div>
					<div class="citation-result">
						<pre id="citation-text"></pre>
					</div>
					<div class="citation-copied" style="display:none;"><?php esc_html_e( 'Citation copied!', 'edmp' ); ?></div>
				</div>
				<div class="citation-error" style="display:none;">
					<?php esc_html_e( 'Error loading citation data.', 'edmp' ); ?>
				</div>
				<div class="citation-controls-footer">
					<button id="close-citation-dialog" class="btn btn-sm btn-default" autofocus>
						<?php esc_html_e( 'Close', 'edmp' ); ?>
					</button>
				</div>
			</div>
		</dialog>
		<script>
			// Pass the DOI to the JavaScript
			let postDOI = "<?php echo esc_js( $doi ); ?>";
		</script>
		<?php

		echo $args['after_widget'];
	}
}
