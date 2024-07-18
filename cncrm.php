<?php
/*
Plugin Name: cncrm
Plugin URI: https://codengine.co/
Description: Integrates WPForms submissions with a custom CRM. This plugin helps you to generate your WPForms leads into your custom CRM.
Version: 1.0
Author: CodeNgine Technologies.
Author URI: https://codengine.co/
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('CNCRM_VERSION', '1.0');
define('CNCRM_DIR', plugin_dir_path(__FILE__));
define('CNCRM_URL', plugin_dir_url(__FILE__));
define('CNCRM_BASE_FILE', plugin_basename(__FILE__));
define('CNCRM_ROOT', dirname(__FILE__));
define('CNCRM_DB_VERSION', '1.0');
define('CNCRM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CNCRM_CURRENT_THEME', get_stylesheet_directory());

// Load plugin textdomain for translations (if applicable)
// load_plugin_textdomain('cncrm', false, basename(dirname(__FILE__)) . '/languages');

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/cncrm-integration-ui.php';
require_once plugin_dir_path(__FILE__) . 'includes/cncrm-settings-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/cncrm-field-mappings.php'; 

class CNCRM_Init {

    public function __construct() {

        add_action('admin_menu', array($this, 'register_wpform_menu_pages')); // Hook for adding admin menus
        add_action('admin_init', array($this, 'register_settings')); // Register settings
        add_action('admin_enqueue_scripts', array($this, 'cncrm_enqueue_admin_scripts')); // Enqueue the script for the AJAX request
        add_action('wp_ajax_get_crm_access_token_ajax', array($this, 'get_crm_access_token_ajax'));
        add_action('wpforms_process_complete', array($this, 'process_wpforms_entry'), 10, 4); // Hook into WPForms to process entries
        add_action('wp_ajax_fetch_wpform_fields', array($this, 'fetch_wpform_fields')); // AJAX handler for fetching WPForm fields
        add_action('wp_ajax_nopriv_fetch_wpform_fields', array($this, 'fetch_wpform_fields'));
        add_action('init', array($this, 'load_css_and_js_files')); // Load CSS and JS files
        
        register_activation_hook(__FILE__, array($this, 'cncrm_activate')); // run on activation of plugin
        register_deactivation_hook(__FILE__, array($this, 'cncrm_deactivate')); // run on deactivation of plugin
        register_uninstall_hook(__FILE__, array('CNCRM_Init', 'cncrm_uninstall')); // run on uninstall

        // Ensure our tables are created when the plugin is activated
        add_action('plugins_loaded', array($this, 'cncrm_create_plugin_tables'));
        add_action('plugins_loaded', array($this,'cncrm_alter_plugin_tables'));
        add_action('plugins_loaded', array($this,'cncrm_alter_cncrm_entries_table'));        
              
        add_filter('plugin_action_links_' . CNCRM_BASE_FILE, array($this, 'cncrm_plugin_action_links')); // Add custom link for our plugin
        add_filter('gettext', array($this, 'change_button_text'), 20, 3); // Hook into gettext to change "Save Changes" text
    }

    // Function to check if WPForms is installed and activated.
    public function is_wpforms_installed_and_activated() {
        if (!class_exists('WPForms')) {
            return false;
        }
        if (!defined('WPFORMS_VERSION')) {
            return false;
        }
        return true;
    }

    // Register settings
    public function register_settings() {
        register_setting('cncrm_options_group', 'cncrm_crm_url');

        add_settings_section(
            'cncrm_section',
            '', // Add your section Name just above the CRM URL label and input box
            null,
            'cncrm-config'
        );

        add_settings_field(
            'cncrm_crm_url',
            'CRM URL',
            array($this, 'crm_url_field'),
            'cncrm-config',
            'cncrm_section'
        );
    }

    // Callback function to change "Save Changes" text
    public function change_button_text($translated_text, $text, $domain) {
        if ('Save Changes' === $translated_text && 'default' === $domain) {
            return 'Send Data';
        }
        return $translated_text;
    }

    // CRM URL field callback
    public function crm_url_field() {
        $crm_url = get_option('cncrm_crm_url');
        echo '<input type="text" name="cncrm_crm_url" value="' . esc_attr($crm_url) . '" class="regular-text">';
    }

    // Register menu item
    public function register_wpform_menu_pages() {
        add_menu_page(
            __('WPForms CN CRM Integration', 'cncrm'), // Page Title
            'CN CRM',                                  // Menu Title
            'manage_options',                          // Capability required to access the menu
            'wpforms-crm-integration',                 // Menu Slug
            'cncrm_render_integration_ui',             // Callback function to render the page content
            'dashicons-admin-generic',                 // Icon URL or Dashicon name
            6                                          // Menu Position
        );
        add_submenu_page(
            'wpforms-crm-integration',             // Parent slug
            'CRM Settings',                        // Page title
            'Settings',                            // Menu title
            'manage_options',                      // Capability
            'cncrm-settings',                      // Menu slug
            'cncrm_settings_page'                  // Callback function
        );
    }

    // Enqueue admin scripts
    function cncrm_enqueue_admin_scripts($hook) {
        // Enqueue scripts only on specific pages
        $is_cncrm_settings_page = strpos($hook, 'cncrm-settings') !== false;
        $is_cncrm_logs_page = strpos($hook, 'cncrm-logs') !== false;

        if (!$is_cncrm_settings_page && !$is_cncrm_logs_page) {
            return;
        }

        // Enqueue scripts and styles
        wp_enqueue_script('jquery');
        wp_enqueue_script('admin-script', CNCRM_URL . 'assets/js/admin-script.js', array('jquery'), CNCRM_VERSION, true);

        // Localize the script with data
        $cncrm_admin_object = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'cncrm_nonce' => wp_create_nonce('cncrm_nonce'),
        );
        wp_localize_script('admin-script', 'cncrm_admin_object', $cncrm_admin_object);

        // Enqueue admin styles if any
        wp_enqueue_style('admin-style', CNCRM_URL . 'assets/css/admin-style.css', array(), CNCRM_VERSION, 'all');
    }

    // Activation hook
    public function cncrm_activate() {
        // Set default options if they don't exist
        if (get_option('cncrm_crm_url') === false) {
            update_option('cncrm_crm_url', '');
        }

        // Create custom database table if needed
        $this->cncrm_create_plugin_tables();
        $this->cncrm_alter_plugin_tables();

        // Add a version to track changes
        update_option('cncrm_db_version', CNCRM_DB_VERSION);
    }

        // Create custom database tables
        public function cncrm_create_plugin_tables() {
            global $wpdb;
            $table_name = $wpdb->prefix . 'cncrm_entries';
            $charset_collate = $wpdb->get_charset_collate();
    
            $sql = "CREATE TABLE IF NOT EXISTS $table_name (
                id INT(11) NOT NULL AUTO_INCREMENT,
                wpform_id INT(11) NOT NULL,
                wpform_entry_id INT(11) NOT NULL,
                crm_entry_id INT(11) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;";
    
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
    
            $form_mappings_table_name = $wpdb->prefix . 'cncrm_form_mappings';
            $sql_mappings = "CREATE TABLE IF NOT EXISTS $form_mappings_table_name (
                id INT(11) NOT NULL AUTO_INCREMENT,
                wpform_id INT(11) NOT NULL,
                form_id INT NOT NULL,
                form_name VARCHAR(255) NOT NULL,
                mappings TEXT NOT NULL,
                crm_field_name VARCHAR(255) NOT NULL,
                wpform_field_name VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;";
    
            dbDelta($sql_mappings);
        }

    function cncrm_alter_cncrm_entries_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cncrm_entries';

        $wpdb->query("ALTER TABLE $table_name ADD COLUMN IF NOT EXISTS crm_url VARCHAR(255) NOT NULL");
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN IF NOT EXISTS crm_token VARCHAR(255) NOT NULL;");
    }

    function cncrm_alter_plugin_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'cncrm_form_mappings';

        // Add missing columns if they don't exist
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN IF NOT EXISTS mappings TEXT NOT NULL");
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN IF NOT EXISTS form_name varchar(255) NOT NULL");
        $wpdb->query("ALTER TABLE $table_name ADD COLUMN IF NOT EXISTS form_id INT(11) NOT NULL;");
    }

    // Deactivation hook
    public function cncrm_deactivate() {
        // Perform deactivation tasks, such as removing scheduled events or transient data

        // Remove any custom scheduled events or cron jobs related to this plugin
        wp_clear_scheduled_hook('cncrm_cron_hook');

        // Clear any transients or temporary data
        delete_transient('cncrm_temp_data');
    }

    // Uninstall hook
    public static function cncrm_uninstall() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cncrm_entries';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");

        $form_mappings_table_name = $wpdb->prefix . 'cncrm_form_mappings';
        $wpdb->query("DROP TABLE IF EXISTS $form_mappings_table_name");

        // Remove options if needed
        delete_option('cncrm_crm_url');
        delete_option('cncrm_api_key');
    }

    // Add custom link for our plugin
    public function cncrm_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=cncrm-settings') . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    // Load CSS and JS files
    public function load_css_and_js_files() {
        wp_enqueue_style('cncrm-main-css', CNCRM_URL . 'assets/css/admin-style.css', array(), CNCRM_VERSION, 'all');
        wp_enqueue_script('cncrm-main-js', CNCRM_URL . 'assets/js/admin-script.js', array('jquery'), CNCRM_VERSION, true);
    }

    // Hook into WPForms to process entries
    public function process_wpforms_entry($fields, $entry, $form_data, $entry_id) {
        // Process entry data here and send to CRM
    }

    // Fetch WPForm fields via AJAX
    public function fetch_wpform_fields() {
        check_ajax_referer('cncrm_nonce', '_ajax_nonce');
    
        // Get the form ID from the POST request
        $form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
        if (!$form_id) {
            wp_send_json_error(array('message' => __('Invalid form ID.', 'cncrm')));
        }
    
        // Retrieve form fields
        $form = wpforms()->form->get($form_id);
        if (!$form) {
            wp_send_json_error(array('message' => __('Form not found.', 'cncrm')));
        }
    
        $fields = !empty($form->post_content) ? json_decode($form->post_content, true) : array();
    
        if (empty($fields['fields'])) {
            wp_send_json_error(array('message' => __('No fields found for this form.', 'cncrm')));
        }
    
        // Generate the HTML for the field mappings
        ob_start();
        foreach ($fields['fields'] as $field) {
            ?>
            <div class="form-field-mapping div-form-label">
                <label for="field_mapping_<?php echo esc_attr($field['id']); ?>" class="lbl_field_names">
                    <?php echo esc_html($field['label']); ?>
                </label>
                <select name="cncrm_field_mappings[<?php echo esc_attr($field['id']); ?>]" id="field_mapping_<?php echo esc_attr($field['id']); ?>" class="cncrm_select_dropdown">
                    <option value=""><?php echo esc_html__('Select Mapping', 'cncrm'); ?></option>
                    <option value="leadName" title="Use Lead Name as per your CRM Lead Name column."><?php echo esc_html__('Lead Name', 'cncrm'); ?></option>
                    <option value="firstName" title="Use First Name as per your CRM First Name column."><?php echo esc_html__('First Name', 'cncrm'); ?></option>
                    <option value="lastName" title="Use Last Name as per your CRM Last Name column."><?php echo esc_html__('Last Name', 'cncrm'); ?></option>
                    <option value="businessEmail" title="Use Business Email as per your CRM Business Email column."><?php echo esc_html__('Business Email', 'cncrm'); ?></option>
                    <option value="companyName" title="Use Company Name as per your CRM Company Name column."><?php echo esc_html__('Company Name', 'cncrm'); ?></option>
                    <option value="companyPhone" title="Use Company Phone as per your CRM Company Phone column."><?php echo esc_html__('Company Phone', 'cncrm'); ?></option>
                    <option value="phone" title="Use Phone as per your CRM Phone column."><?php echo esc_html__('Phone', 'cncrm'); ?></option>
                    <option value="email" title="Use Email as per your CRM Email column."><?php echo esc_html__('Email', 'cncrm'); ?></option>
                    <option value="location" title="Use Location as per your CRM Location column."><?php echo esc_html__('Location', 'cncrm'); ?></option>
                    <option value="address" title="Use Address as per your CRM Address column."><?php echo esc_html__('Address', 'cncrm'); ?></option>
                    <option value="message" title="Use Message as per your CRM Message column."><?php echo esc_html__('Message', 'cncrm'); ?></option>
                </select>
            </div>
            <?php
        }
        $html = ob_get_clean();
    
        wp_send_json_success(array('html' => $html));
    }
    // Function to get CRM access token via AJAX
    public function get_crm_access_token_ajax() {
        check_ajax_referer('cncrm_nonce', 'nonce');
    
        $access_token = get_option('cncrm_crm_access_token', '');
    
        if (empty($access_token)) {
            wp_send_json_error(array('message' => 'CRM Access Token not found.'));
        } else {
            wp_send_json_success(array('access_token' => $access_token));
        }
    }
}

// Instantiate the plugin class
if (class_exists('CNCRM_Init')) {
    $cncrm_init = new CNCRM_Init();
}
?>
