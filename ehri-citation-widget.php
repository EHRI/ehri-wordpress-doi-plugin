<?php
/**
 * Plugin Name: Post Citation Widget
 * Description: Displays citation options for posts with DOIs
 * Version: 1.0
 * Author: Your Name
 */

// Register the widget
function register_citation_widget()
{
    register_widget('Ehri_Citation_Widget');
}

add_action('widgets_init', 'register_citation_widget');

// Enqueue Citation.js from CDN
function enqueue_citation_js()
{
    wp_enqueue_style('ehri-citation-widget-css', plugins_url('css/ehri-citation-widget.css', __FILE__), array(), '1.0.0');
    wp_enqueue_script('citation-js', 'https://cdn.jsdelivr.net/npm/citation-js@0.7.18/build/citation.min.js', array(), null, true);
    wp_enqueue_script('ehri-citation-widget-js', plugin_dir_url(__FILE__) . 'js/ehri-citation-widget.js', array('jquery', 'citation-js'), '1.0', true);
}

add_action('wp_enqueue_scripts', 'enqueue_citation_js');

// Widget class
class Ehri_Citation_Widget extends WP_Widget
{

    function __construct()
    {
        parent::__construct(
            'ehri_citation_widget',
            'EHRI Citation Widget',
            array('description' => 'Displays citation options for posts with DOIs')
        );
    }

    // Frontend display
    public function widget($args, $instance)
    {
        global $post;

        // Only show on single posts
        if (!is_single()) {
            return;
        }

        // Check if the post has a DOI
        $doi = get_post_meta($post->ID, '_doi', true);
        if (empty($doi)) {
            return;
        }

        echo $args['before_widget'];
        echo $args['before_title'] . '<a href="#" id="show-citation-dialog">Cite This Article</a>' . $args['after_title'];
        ?>
        <dialog class="citation-container">
            <div class="citation-loading">Loading citation data...</div>
            <div class="citation-formats" style="display:none;">
                <div class="citation-controls">
                    <select id="citation-format-selector">
                        <option value="apa">APA</option>
                        <option value="mla">MLA</option>
                        <option value="chicago">Chicago</option>
                        <option value="harvard">Harvard</option>
                        <option value="bibtex">BibTeX</option>
                        <option value="ris">RIS</option>
                    </select>
                    <button id="copy-citation" class="btn btn-sm">
                        <i class="fa fa-copy"></i>
                        Copy
                    </button>
                </div>
                <div class="citation-result">
                    <pre id="citation-text"></pre>
                </div>
                <div class="citation-copied" style="display:none;">Citation copied!</div>
            </div>
            <div class="citation-error" style="display:none;">
                Error loading citation data.
            </div>
            <div class="citation-controls-footer">
                <button id="close-citation-dialog" class="btn btn-sm btn-default" autofocus>Close</button>
            </div>
        </dialog>
        <script>
          // Pass the DOI to the JavaScript
          var postDOI = "<?php echo esc_js($doi); ?>";
          var Cite;
        </script>
        <?php

        echo $args['after_widget'];
    }
}