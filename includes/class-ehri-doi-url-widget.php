<?php
/**
 * Plugin Name: DOI URL Widget
 * Description: Displays a DOI URL
 * Version: 1.0
 *
 * @package ehri-doi-url-widget
 */

if ( ! function_exists( 'register_doi_url_widget' ) ) {
	/**
	 * Register the DOI URL widget.
	 *
	 * @return void
	 */
	function register_doi_url_widget() {
		register_widget( 'EHRI_DOI_Url_Widget' );
	}
}

add_action( 'widgets_init', 'register_doi_url_widget' );


if ( ! function_exists( 'enqueue_doi_url_widget_css' ) ) {
	/**
	 * Enqueue the CSS for the DOI URL widget.
	 *
	 * @return void
	 */
	function enqueue_doi_url_widget_css() {
		wp_enqueue_style( 'ehri-doi-url-css', plugins_url( 'css/ehri-doi-url.css', EHRI_DOI_PLUGIN_PATH ), array(), '1.0.0' );
		wp_enqueue_style( 'ehri-doi-url-widget-css', plugins_url( 'css/ehri-doi-url-widget.css', EHRI_DOI_PLUGIN_PATH ), array(), '1.0.0' );
	}
}

add_action( 'wp_enqueue_scripts', 'enqueue_doi_url_widget_css' );


/**
 * EHRI DOI URL Widget
 */
class EHRI_DOI_Url_Widget extends WP_Widget {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->resolver_url_prefix = get_option(
			'_ehri_doi_plugin_options',
			array(
				'resolver_url_prefix' => 'https://doi.org/',
			)
		)['resolver_url_prefix'];

		parent::__construct(
			'EHRI_DOI_Url_Widget',
			'DOI URL Widget [EHRI]',
			array( 'description' => "Displays a DOI URL if '_doi' and '_doi_state' metadata is available" )
		);
	}

	/**
	 * Widget form creation.
	 *
	 * @param array $args The widget arguments.
	 * @param array $instance The widget instance.
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
		if ( empty( $doi ) ) {
			echo '<!-- No DOI available in post metadata -->';
			return;
		}

		if ( is_user_logged_in() || $this->doi_visible( $state ) ) {
			$this->render_doi_url( $doi, $state, $args );
		}
	}

	/**
	 * Check if the DOI is visible.
	 *
	 * @param string $state The DOI state.
	 * @return bool True if the DOI is visible, false otherwise.
	 */
	private function doi_visible( string $state ): bool {
		return 'findable' === $state || 'registered' === $state;
	}

	/**
	 * Render the DOI URL in the container widget.
	 *
	 * @param string $doi The DOI.
	 * @param string $state The DOI state.
	 * @param array  $args The widget arguments.
	 * @return void
	 */
	private function render_doi_url( string $doi, string $state, array $args ) {
		ob_start();
		echo wp_kses_post( $args['before_widget'] );
		?>
		<div class="doi-url-container">
			<a href="<?php echo esc_attr( $this->resolver_url_prefix ); ?><?php echo esc_attr( $doi ); ?>" target="_blank">
				<?php echo esc_attr( $this->resolver_url_prefix ); ?><?php echo esc_html( $doi ); ?>
			</a>
			<?php if ( is_user_logged_in() ) : ?>
			<span class="doi-state-container doi-state-<?php echo esc_attr( $state ); ?>">
				<span class="doi-state-icon"></span>
				<span class="doi-state-text"><?php echo esc_html( $state ); ?></span>
			</span>
			<?php endif; ?>
		</div>
		<?php
		echo $args['after_widget'];
	}
}
