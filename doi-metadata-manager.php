<?php
/**
 * Plugin Name: DOI Metadata Manager
 * Description: Adds DOI metadata management capabilities to WordPress posts
 * Version: 1.0.0
 * Author: Your Name
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// Include DOI renderer
require_once plugin_dir_path(__FILE__) . 'doi-metadata-renderer.php';

// Include admin panel functionality
require_once plugin_dir_path(__FILE__) . 'admin/class-doi-metadata-admin.php';

// Include citation widget
require_once plugin_dir_path(__FILE__) . 'ehri-citation-widget.php';


class DOI_Metadata_Manager
{

    public function __construct()
    {
        // Initialize admin panel
        $this->admin = new DOI_Metadata_Admin();

        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Add meta box to post edit screen
        add_action('add_meta_boxes', array($this, 'add_doi_meta_box'));

        // Save post meta
        add_action('save_post', array($this, 'save_meta'));

        // Register scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'register_assets'));

        // Ajax handlers
        add_action('wp_ajax_open_doi_modal', array($this, 'ajax_open_modal'));
        add_action('wp_ajax_save_doi_metadata', array($this, 'ajax_save_metadata'));
        add_action('wp_ajax_register_doi_metadata', array($this, 'ajax_register_doi_metadata'));
    }

    public function activate()
    {
        // Setup plugin on activation
        // (create database tables if needed)
    }

    public function add_doi_meta_box()
    {
        add_meta_box(
            'doi-metadata-box',
            'DOI Metadata',
            array($this, 'render_meta_box'),
            'post',
            'side',
            'default'
        );
    }

    public function render_meta_box($post)
    {
        // Get existing metadata if any
        $doi = get_post_meta($post->ID, '_doi', true);
        $state = get_post_meta($post->ID, '_doi_state', true);

        wp_nonce_field('doi_metadata_nonce', 'doi_metadata_nonce');
        ?>
        <div class="doi-metadata-panel">
            <dl>
                <dt>DOI:</dt>
                <dd><?php echo esc_attr($doi); ?></dd>
                <dt>State:</dt>
                <dd>
                    <span class="doi-state-container doi-state-<?php echo esc_attr($state); ?>">
                        <span class="doi-state-icon"></span>
                        <span><?php echo esc_attr(ucfirst($state)); ?></span>
                    </span>
                </dd>
            </dl>

            <button type="button" id="edit_doi_button" class="button">Manage DOI</button>
        </div>
        <?php
    }

    public function save_meta($post_id)
    {
        // Verify nonce and user permissions
        if (!isset($_POST['doi_metadata_nonce']) ||
            !wp_verify_nonce($_POST['doi_metadata_nonce'], 'doi_metadata_nonce') ||
            !current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save simple DOI field (full metadata saved via AJAX)
        if (isset($_POST['doi_field'])) {
            update_post_meta($post_id, '_doi', sanitize_text_field($_POST['doi_field']));
        }
    }

    public function register_assets($hook)
    {
        // Only load on post edit screen
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }

//        wp_enqueue_script('petite-vue', 'https://unpkg.com/petite-vue', array(), '2.0.0', true);

        wp_enqueue_script(
            'doi-metadata-js',
            plugins_url('js/doi-metadata.js', __FILE__),
            array('jquery', 'jquery-ui-dialog'),
            '1.0.0',
            true
        );

        wp_enqueue_style(
            'doi-metadata-css',
            plugins_url('css/doi-metadata.css', __FILE__),
            array('wp-jquery-ui-dialog'),
            '1.0.0'
        );

        // Pass data to JavaScript
        wp_localize_script('doi-metadata-js', 'doiMetadata', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'providerBaseUrl' => $this->admin->get_active_provider_url(),
            'nonce' => wp_create_nonce('doi_metadata_nonce')
        ));
    }

    public function ajax_open_modal()
    {
        // Verify nonce and permissions
        check_ajax_referer('doi_metadata_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
        }

        // Get all metadata
        $doi = get_post_meta($post_id, '_doi', true);
        if (!empty($doi)) {
            $this->fetch_doi_metadata($post_id, $doi);
        } else {
            $this->initialize_doi_metadata($post_id);
        }

    }

    /**
     * Fetch titles for the post in all the languages for which
     * it is available (via Polylang, if installed).
     *
     * @param $post_id
     * @return array
     */
    private function get_title_info($post_id)
    {
        if (function_exists('pll_get_post_language')) {
            $titles = array();
            $translations = pll_get_post_translations($post_id);
            foreach ($translations as $lang => $translation_id) {
                $titles[] = array(
                    "title" => $this->clean_text(get_post($translation_id)->post_title),
                    "lang" => $lang
                );
            }
            return $titles;
        } else {
            return array("title" => $this->clean_text(get_post($post_id)->post_title));
        }
    }

    /**
     * Get the description for the post in all the languages for which
     * it is available (via Polylang, if installed).
     *
     * @param $post_id
     * @return array
     */
    private function get_description_info($post_id)
    {
        if (function_exists('pll_get_post_language')) {
            $descriptions = array();
            $translations = pll_get_post_translations($post_id);
            foreach ($translations as $lang => $translation_id) {
                $descriptions[] = array(
                    "description" => $this->clean_text(get_the_excerpt($translation_id)),
                    "lang" => $lang
                );
            }
            return $descriptions;
        } else {
            return array(
                "description" => $this->clean_text(get_the_excerpt($post_id)),
            );
        }
    }

    private function get_author_info($post_id): array
    {
        $authors = array();
        if (function_exists('coauthors_posts_links')) {
            $coauthors = get_coauthors($post_id);
            foreach ($coauthors as $coauthor) {
                $authors[] = [
                    'givenName' => $coauthor->first_name,
                    'familyName' => $coauthor->last_name,
                    'name' => $coauthor->display_name,
                    'contributorType' => 'Author'
                ];
            }
        } else {
            $author = get_the_author_meta('display_name', $post_id);
            // Hacky way to split first and last name
            $parts = explode(' ', $author, 2);
            $authors[] = [
                'givenName' => $parts[0],
                'familyName' => $parts[1],
                'name' => $author,
                'contributorType' => 'Author'
            ];
        }
        return $authors;
    }

    /**
     * Returns the publication year of the post if it is published.
     * Otherwise returns the current year.
     *
     * @param $post_id int the post id
     * @return false|int|string
     */
    private function get_publication_year($post_id)
    {
        $post = get_post($post_id);
        if ($post->post_status === 'publish') {
            return get_the_date('Y', $post);
        } else {
            return date('Y');
        }
    }

    /**
     * Returns an array of date objects for
     * publication and modification.
     *
     * @param $post_id
     * @return array
     */
    private function get_date_info($post_id): array
    {
        $post = get_post($post_id);

        $dates = array();
        if ($post->post_status === 'publish') {
            if ($pub = get_the_date('Y-m-d', $post)) {
                $dates[] = array(
                    "date" => $pub,
                    "dateType" => "Created"
                );
            }

            if ($mod = get_the_modified_date('Y-m-d', $post)) {
                $dates[] = array(
                    "date" => $mod,
                    "dateType" => "Updated"
                );
            }
        }

        return $dates;
    }

    /**
     * Fetch relevant alternative identifiers for the post.
     *
     * @param $post_id int the post ID
     * @return array
     */
    private function get_alternative_identifier_info($post_id): array
    {
        $post = get_post($post_id);

        $alts = array(
            array(
                "alternateIdentifier" => $post->ID,
                "alternateIdentifierType" => "Post ID"
            ),
        );
        if ($slug = $post->post_name) {
            $alts[] = array(
                "alternateIdentifier" => $slug,
                "alternateIdentifierType" => "Slug"
            );
        }
        return $alts;
    }

    /**
     * Get the DOI metadata from the post information and the
     * plugin settings.
     *
     * @param $post_id int the post ID
     * @param $ajax bool send data as an Ajax response
     * @return void|array
     */
    private function initialize_doi_metadata($post_id, $ajax = true): array
    {
        $data = array(
            'titles' => $this->get_title_info($post_id),
            'descriptions' => $this->get_description_info($post_id),
            'creators' => $this->get_author_info($post_id),
            'publisher' => $this->admin->get_options()['publisher'],
            'publicationYear' => $this->get_publication_year($post_id),
            'dates' => $this->get_date_info($post_id),
            'alternativeIdentifiers' => $this->get_alternative_identifier_info($post_id),
            "formats" => array("text/html"),
            'types' => array(
                'resourceType' => 'Blog Post',
                'resourceTypeGeneral' => 'Text'
            )
        );

        if ($ajax) {
            // Send data for the modal
            wp_send_json_success(array(
                'data' => $data,
                'modal_html' => $this->get_modal_html($post_id, $data)
            ));
        }

        return $data;
    }

    private function fetch_doi_metadata($post_id, $doi, $ajax = true)
    {
        // Fetch metadata from DataCite API
        $url = sprintf("%s/%s", $this->admin->get_service_url(), $doi);
        error_log('Fetching DOI metadata: ' . $url . " DOI: " . $doi);
        $api_response = wp_remote_get($url, array(
            'headers' => array(
                'Accept' => 'application/vnd.api+json',
                'Authorization' => 'Basic ' . $this->admin->get_active_provider_auth()
            )
        ));

        if (is_wp_error($api_response)) {
            wp_send_json_error('Error querying DataCite API');
        }

        $data = json_decode(wp_remote_retrieve_body($api_response), true);

        error_log(print_r($data, true));

        if (isset($data['errors'])) {
            error_log('Error fetching DOI metadata: ' . print_r($data, true));
            wp_send_json_error('Error fetching DOI metadata');
        }

        if ($ajax) {
            // Send data for the modal
            wp_send_json_success(array(
                'data' => $data,
                'modal_html' => $this->get_modal_html($post_id, $data["data"]["attributes"], $doi)
            ));
        }

        return $data;
    }

    public function ajax_register_doi_metadata()
    {
        // Verify nonce and permissions
        check_ajax_referer('doi_metadata_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

        error_log('Registering DOI metadata: ' . $post_id);
        error_log(print_r($_POST, true));

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error('Permission denied');
        }

        $doi = get_post_meta($post_id, '_doi', true);

        if ($doi) {
            $this->update_doi_metadata($post_id, $doi);
        } else {
            $this->create_doi($post_id);
        }

    }

    private function update_doi_metadata($post_id, $doi)
    {
        $existing = $this->fetch_doi_metadata($post_id, $doi, false);
        $attrs = $existing["data"]["attributes"];

        $post_data = $this->initialize_doi_metadata($post_id, false);
        $publish = $_POST['publish_doi'] ?? false;

        // Prepare the metadata payload
        $metadata = array(
            'data' => array(
                'type' => 'dois',
                'attributes' => array(
                    'event' => $publish ? 'draft' : 'publish', // Defaults to draft
                    'prefix' => $attrs["prefix"],
                    'doi' => $attrs["doi"],
                    'url' => $attrs["url"],
                )
            )
        );
        // Set the existing values
        foreach ($attrs as $key => $value) {
            $metadata["data"]["attributes"][$key] = $value;
        }
        // Override with any changes values from the post
        foreach ($post_data as $key => $value) {
            $metadata["data"]["attributes"][$key] = $value;
        }

        // Send the metadata to the DataCite API
        $api_response = wp_remote_post($this->admin->get_service_url(), array(
            'method' => 'PUT',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $this->admin->get_active_provider_auth()
            ),
            'body' => json_encode(array(
                "target" => get_the_permalink($post_id),
                "metadata" => $metadata
            ))
        ));

        if (is_wp_error($api_response)) {
            error_log('API error registering DOI metadata: ' . $api_response->get_error_message());
            wp_send_json_error('API error updating DOI metadata');
        }

        $data = json_decode(wp_remote_retrieve_body($api_response), true);
        error_log("Service Response: " . json_encode($data));

        if (isset($data['errors'])) {
            error_log('Response error registering DOI metadata: ' . print_r($data, true));
            wp_send_json_error('Response error updating DOI metadata');
        }

        // Save the updated post info
        $this->save_doi_post_metadata($post_id, $doi, $data);

        wp_send_json_success(array(
            'message' => 'DOI metadata updated successfully: DOI ' . $doi,
            'doi' => $doi
        ));
    }

    private function create_doi($post_id)
    {

        // Fetch the attributes from the form POST data
        $post_data = $this->initialize_doi_metadata($post_id, false);

        // Prepare the metadata payload
        $metadata = array(
            'data' => array(
                'type' => 'dois',
                'attributes' => array(
//                    'event' => 'register', // Defaults to draft
                    'prefix' => $this->admin->get_options()['prefix'],
                    'url' => 'http://pids.local/doi/', // This will be set to the appropriate landing page on publication
                )
            )
        );
        // set post data
        foreach ($post_data as $key => $value) {
            $metadata["data"]["attributes"][$key] = $value;
        }

        // For the time being log the metadata to the console
//        error_log(print_r($metadata, true));

        // Send the metadata to the DataCite API
        $api_response = wp_remote_post($this->admin->get_service_url(), array(
            'method' => 'PUT',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . $this->admin->get_active_provider_auth()
            ),
            'body' => json_encode(array(
                "target" => get_the_permalink($post_id),
                "metadata" => $metadata
            ))
        ));

        if (is_wp_error($api_response)) {
            error_log('API error registering DOI metadata: ' . $api_response->get_error_message());
            wp_send_json_error('API error registering DOI metadata');
        }

        $data = json_decode(wp_remote_retrieve_body($api_response), true);
        error_log("Service Response: " . json_encode($data));

        if (isset($data['errors'])) {
            error_log('Response error registering DOI metadata: ' . print_r($data, true));
            wp_send_json_error('Response error registering DOI metadata');
        }

        $doi = $data['data']['id'];
        $this->save_doi_post_metadata($post_id, $doi, $metadata);

        wp_send_json_success(array(
            'message' => 'DOI metadata registered successfully: DOI ' . $doi,
            'doi' => $doi
        ));
    }

    private function get_modal_html($post_id, $data, $doi = null)
    {
        $renderer = new Doi_Metadata_Renderer($data, $doi);
        ob_start();
        ?>
        <div id="doi-metadata-modal" title="Manage DOI Metadata">
            <form id="doi-metadata-form">
                <input type="hidden" name="post_id" value="<?php echo $post_id; ?>"/>

                <?php echo $renderer->getCSS(); ?>
                <?php echo $renderer->render(); ?>

                <div class="button-row">
                    <!--                    <button type="button" id="save-doi-metadata" class="button button-primary">Save Metadata</button>-->
                    <button type="button" id="register-doi-metadata" class="button button-primary">Register DOI</button>
                    <!--                    <button type="button" id="fetch-doi-data" class="button">Fetch from DOI</button>-->
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function clean_text($text)
    {
        return html_entity_decode($text, ENT_HTML5 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Save the DOI and its state to the post metadata and
     * that of any translations.
     *
     * @param $post_id int the post ID
     * @param $doi string the DOI
     * @param $metadata array the full DataCite metadata object
     * @return void
     */
    private function save_doi_post_metadata($post_id, $doi, $metadata): void
    {
        // Save the DOI to the post meta
        $state = $metadata["data"]["attributes"]["state"] ?? 'draft';
        if (function_exists('pll_get_post_translations')) {
            foreach (pll_get_post_translations($post_id) as $lang => $translation_id) {
                update_post_meta($translation_id, '_doi', $doi);
                update_post_meta($translation_id, '_doi_state', $state);
            }
        } else {
            update_post_meta($post_id, '_doi', $doi);
            update_post_meta($post_id, '_doi_state', $state);
        }
    }
}

// Initialize the plugin
$doi_metadata_manager = new DOI_Metadata_Manager();
