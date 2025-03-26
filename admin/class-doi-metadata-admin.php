<?php
/**
 * DOI Metadata Manager - Admin Panel
 *
 * Adds an admin settings page to configure the DOI Metadata plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

class DOI_Metadata_Admin
{

    // Option names with the requested prefix
    private $option_prefix = '_ehri_doi_plugin';
    private $options;

    public function __construct()
    {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Load options
        $this->options = get_option($this->option_prefix . '_options', array(
            'publisher' => '',
            'provider_url' => 'https://doi.datacite.org/dois/',
            'is_test_environment' => true,
            'test_provider_url' => 'https://doi.test.datacite.org/dois/',
            'test_provider_repository' => '',
            'test_provider_password' => '',
            'prefix' => '10.1234', // Default DOI prefix
        ));
    }

    public function add_admin_menu()
    {
        add_options_page(
            'DOI Metadata Settings',
            'DOI Metadata',
            'manage_options',
            'doi-metadata-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings()
    {
        // Register a single option to store all our settings
        register_setting(
            'doi_metadata_settings',
            $this->option_prefix . '_options',
            array($this, 'sanitize_settings')
        );

        // Publisher Settings Section
        add_settings_section(
            'doi_publisher_section',
            'Publisher Information',
            array($this, 'publisher_section_callback'),
            'doi-metadata-settings'
        );

        add_settings_field(
            'publisher',
            'Publisher Name',
            array($this, 'publisher_field_callback'),
            'doi-metadata-settings',
            'doi_publisher_section'
        );

        add_settings_field(
            'prefix',
            'DOI Prefix',
            array($this, 'prefix_field_callback'),
            'doi-metadata-settings',
            'doi_publisher_section'
        );

        // DOI Provider Settings Section
        add_settings_section(
            'doi_provider_section',
            'DOI Provider Settings',
            array($this, 'provider_section_callback'),
            'doi-metadata-settings'
        );

        add_settings_field(
            'service_url',
            'DOI Registration Service',
            array($this, 'service_url_field_callback'),
            'doi-metadata-settings',
            'doi_provider_section'
        );

        add_settings_field(
            'provider_url',
            'Production DOI Provider URL',
            array($this, 'provider_url_field_callback'),
            'doi-metadata-settings',
            'doi_provider_section'
        );

        add_settings_field(
            'is_test_environment',
            'Use Test Environment',
            array($this, 'test_environment_field_callback'),
            'doi-metadata-settings',
            'doi_provider_section'
        );

        add_settings_field(
            'test_provider_url',
            'Test DOI Provider URL',
            array($this, 'test_provider_url_field_callback'),
            'doi-metadata-settings',
            'doi_provider_section'
        );

        add_settings_field(
            'test_provider_repository',
            'Test DOI Provider Repository',
            array($this, 'test_provider_repository_field_callback'),
            'doi-metadata-settings',
            'doi_provider_section'
        );

        add_settings_field(
            'test_provider_password',
            'Test DOI Provider Password',
            array($this, 'test_provider_password_field_callback'),
            'doi-metadata-settings',
            'doi_provider_section',
            array('type' => 'password')
        );
    }

    public function sanitize_settings($input)
    {
        $output = array();

        // Sanitize publisher
        $output['publisher'] = sanitize_text_field($input['publisher']);

        // Sanitize DOI prefix
        $output['prefix'] = sanitize_text_field($input['prefix']);

        // Sanitize provider URLs
        $output['service_url'] = esc_url_raw($input['service_url']);
        $output['provider_url'] = esc_url_raw($input['provider_url']);
        $output['test_provider_url'] = esc_url_raw($input['test_provider_url']);
        $output['test_provider_repository'] = sanitize_text_field($input['test_provider_repository']);
        $output['test_provider_password'] = sanitize_text_field($input['test_provider_password']);

        // Sanitize checkbox
        $output['is_test_environment'] = isset($input['is_test_environment']) ? (bool)$input['is_test_environment'] : false;

        return $output;
    }

    // Section callbacks
    public function publisher_section_callback()
    {
        echo '<p>Configure the DOI publisher information that will be used for all DOIs generated by this plugin.</p>';
    }

    public function provider_section_callback()
    {
        echo '<p>Configure the DOI provider URLs for both production and test environments.</p>';
    }

    // Field callbacks
    public function publisher_field_callback()
    {
        $value = isset($this->options['publisher']) ? $this->options['publisher'] : '';
        echo '<input type="text" id="publisher" name="' . $this->option_prefix . '_options[publisher]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">The name of the organization or publisher responsible for issuing DOIs.</p>';
    }

    public function prefix_field_callback()
    {
        $value = isset($this->options['prefix']) ? $this->options['prefix'] : '10.1234';
        echo '<input type="text" id="prefix" name="' . $this->option_prefix . '_options[prefix]" value="' . esc_attr($value) . '" />';
        echo '<p class="description">Your registered DOI prefix (e.g., 10.1234)</p>';
    }

    public function service_url_field_callback()
    {
        $value = isset($this->options['service_url']) ? $this->options['service_url'] : '';
        echo '<input type="url" id="service_url" name="' . $this->option_prefix . '_options[service_url]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">The base URL for the DOI registration service</p>';
    }

    public function provider_url_field_callback()
    {
        $value = isset($this->options['provider_url']) ? $this->options['provider_url'] : 'https://doi.org/doi/';
        echo '<input type="url" id="provider_url" name="' . $this->option_prefix . '_options[provider_url]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">The base URL for the DOI provider (production environment)</p>';
    }

    public function test_environment_field_callback()
    {
        $checked = isset($this->options['is_test_environment']) && $this->options['is_test_environment'] ? 'checked' : '';
        echo '<input type="checkbox" id="is_test_environment" name="' . $this->option_prefix . '_options[is_test_environment]" ' . $checked . ' />';
        echo '<label for="is_test_environment">Check this box to use the test environment URL</label>';
    }

    public function test_provider_url_field_callback()
    {
        $value = isset($this->options['test_provider_url']) ? $this->options['test_provider_url'] : 'https://doi.test.datacite.org/dois/';
        echo '<input type="url" id="test_provider_url" name="' . $this->option_prefix . '_options[test_provider_url]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">The base URL for the DOI provider test environment</p>';
    }

    public function test_provider_repository_field_callback()
    {
        $value = isset($this->options['test_provider_repository']) ? $this->options['test_provider_repository'] : '';
        echo '<input type="text" id="test_provider_repository" name="' . $this->option_prefix . '_options[test_provider_repository]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">The repository ID for the test environment</p>';
    }

    public function test_provider_password_field_callback()
    {
        $value = isset($this->options['test_provider_password']) ? $this->options['test_provider_password'] : '';
        echo '<input type="password" id="test_provider_password" name="' . $this->option_prefix . '_options[test_provider_password]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">The password for the test environment</p>';
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('doi_metadata_settings');
                do_settings_sections('doi-metadata-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    // Helper method to get current options (can be used by main plugin class)
    public function get_options()
    {
        return $this->options;
    }

    public function get_service_url()
    {
        return $this->options['service_url'];
    }

    // Helper to get the active provider URL based on environment setting
    public function get_active_provider_url()
    {
        if (isset($this->options['is_test_environment']) && $this->options['is_test_environment']) {
            return $this->options['test_provider_url'];
        }
        return $this->options['provider_url'];
    }

    // Helper to get the repository and password encoded as base64
    public function get_active_provider_auth()
    {
        if (isset($this->options['is_test_environment']) && $this->options['is_test_environment']) {
            return base64_encode($this->options['test_provider_repository'] . ':' . $this->options['test_provider_password']);
        }
        return '';
    }
}