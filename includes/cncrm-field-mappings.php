<?php
// cncrm-field-mappings.php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class CNCRM_Field_Mappings {
    public function __construct() {
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Handle form submission processing
        add_action('wpforms_process_complete', array($this, 'process_form_submission'), 10, 4);

        // AJAX handler for saving mappings
        add_action('wp_ajax_save_field_mappings', array($this, 'save_field_mappings'));

        add_action('wp_ajax_fetch_field_mappings', 'cncrm_fetch_field_mappings');
        add_action('wp_ajax_nopriv_fetch_field_mappings', 'fetch_field_mappings');

        //Handle WPForms submissions
        add_action('wpforms_process_complete', array($this, 'send_to_custom_crm'), 10, 4);

        add_action('wp_ajax_send_data_to_crm', 'send_data_to_crm');
        add_action('wp_ajax_nopriv_send_data_to_crm', 'send_data_to_crm');
    }

    public function register_settings() {
        register_setting('cncrm_options_group', 'cncrm_field_mappings', array($this, 'sanitize'));

        add_settings_section(
            'cncrm_section',
            __('CRM Field Mappings', 'cncrm'),
            null,
            'cncrm-config'
        );

        $fields = $this->get_wpform_fields();
        if (!empty($fields)) {
            foreach ($fields as $field) {
                add_settings_field(
                    $field['id'],
                    $field['label'],
                    array($this, 'field_mapping_dropdown'),
                    'cncrm-config',
                    'cncrm_section',
                    array('field' => $field)
                );
            }
        }
    }

    public function field_mapping_dropdown($args) {
        $field = $args['field'];
        $mappings = get_option('cncrm_field_mappings', array());
        $value = isset($mappings[$field['id']]) ? $mappings[$field['id']] : '';
        echo '<select name="cncrm_field_mappings[' . esc_attr($field['id']) . ']">
            <option value="">' . __('Select Mapping', 'cncrm') . '</option>
            <option value="leadName"' . selected($value, 'leadName', false) . '>Lead Name</option>
            <option value="firstName"' . selected($value, 'firstName', false) . '>First Name</option>
            <option value="lastName"' . selected($value, 'lastName', false) . '>Last Name</option>
            <option value="businessEmail"' . selected($value, 'businessEmail', false) . '>Business Email</option>
            <option value="CompanyName"' . selected($value, 'companyName', false) . '>Company Name</option>
            <option value="companyPhone"' . selected($value, 'companyPhone', false) . '>Company Phone</option>
            <option value="phone"' . selected($value, 'phone', false) . '>Phone</option>
            <option value="email"' . selected($value, 'email', false) . '>Email</option>
            <option value="location"' . selected($value, 'location', false) . '>Location</option>
            <option value="address"' . selected($value, 'address', false) . '>Address</option>
            <option value="message"' . selected($value, 'message', false) . '>Message</option>
        </select>';
    }

    public function sanitize($input) {
        $sanitized_input = array();
        foreach ($input as $key => $value) {
            $sanitized_input[$key] = sanitize_text_field($value);
        }
        return $sanitized_input;
    }

    function save_field_mappings() {
        check_ajax_referer('cncrm_nonce', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
    
        global $wpdb;
    
        $table_name = $wpdb->prefix . 'cncrm_form_mappings';
    
        $form_id = intval($_POST['form_id']);
        $form_name = sanitize_text_field($_POST['form_name']);
        $mappings = $_POST['mappings'];
    
        // Serialize the mappings array
        $mappings_serialized = maybe_serialize($mappings);
    
        // Check if the mapping already exists
        $existing_mapping = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE form_id = %d",
                $form_id
            )
        );
    
        if ($existing_mapping) {
            // Update the existing mapping
            $result = $wpdb->update(
                $table_name,
                array(
                    'form_name' => $form_name,
                    'mappings' => $mappings_serialized,
                ),
                array('form_id' => $form_id),
                array(
                    '%s', // form_name
                    '%s' // mappings
                ),
                array('%d') // form_id
            );
        } else {
            // Insert new mapping
            $result = $wpdb->insert(
                $table_name,
                array(
                    'form_id' => $form_id,
                    'form_name' => $form_name,
                    'mappings' => $mappings_serialized,
                ),
                array(
                    '%d', // form_id
                    '%s', // form_name
                    '%s' // mappings
                )
            );
        }
    
        if ($result === false) {
            wp_send_json_error(array('message' => 'Database error: ' . $wpdb->last_error));
        } else {
            wp_send_json_success();
        }
    }

    public function process_form_submission($fields, $entry, $form_data, $entry_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cncrm_form_mappings';

        $mappings = $wpdb->get_results("SELECT * FROM $table_name", OBJECT_K);

        $mapped_data = array();
        foreach ($mappings as $mapping) {
            $field_id = $mapping->field_id;
            $mapped_field = $mapping->mapped_field;

            if (isset($fields[$field_id])) {
                $mapped_data[$mapped_field] = sanitize_text_field($fields[$field_id]);
            }
        }

        // Save $mapped_data to your CRM or do whatever processing you need
        // Example: Save to another table or send to an external API
    }

    public function get_wpform_fields() {
        // Implement your logic to get WPForms fields here
        return array(
            array('id' => 'field_1', 'label' => 'Field 1'),
            array('id' => 'field_2', 'label' => 'Field 2'),
            // Add other fields as needed
        );
    }

    // Fetch field mappings for a form
function cncrm_fetch_field_mappings() {
    check_ajax_referer('cncrm_nonce', '_ajax_nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied'));
    }

    $form_id = intval($_POST['form_id']);

    global $wpdb;
    $table_name = $wpdb->prefix . 'cncrm_form_mappings';

    $mappings = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT mappings FROM $table_name WHERE form_id = %d",
            $form_id
        ),
        ARRAY_A
    );

    if ($mappings) {
        $mappings = maybe_unserialize($mappings['mappings']);
        wp_send_json_success(array('mappings' => $mappings));
    } else {
        wp_send_json_error(array('message' => 'Mappings not found for the specified form.'));
    }
}

// Send data to CRM endpoint
private function send_data_to_crm($crm_url, $api_key, $data) {
    // Prepare headers
    $headers = array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . $api_key,
    );

    // Prepare API request
    $args = array(
        'headers' => $headers,
        'body' => json_encode($data),
        'timeout' => 20,
        'sslverify' => false, // Only use this if your CRM endpoint is on HTTP without SSL
    );

    // Make the API request
    $response = wp_remote_post($crm_url, $args);

    // Check for errors
    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => $response->get_error_message(),
        );
    }

    // Check response code
    $response_code = wp_remote_retrieve_response_code($response);
    if (200 !== $response_code) {
        return array(
            'success' => false,
            'message' => 'Error sending data to CRM. Response code: ' . $response_code,
        );
    }

    // Get the response body
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Check for success in the CRM response
    if (isset($data['success']) && $data['success']) {
        return array(
            'success' => true,
            'data' => $data, // Include data in the response to access CRM token
            'message' => 'Data sent successfully to CRM.',
        );
    } else {
        $error_message = isset($data['message']) ? $data['message'] : 'Unknown error';
        return array(
            'success' => false,
            'message' => 'Error sending data to CRM: ' . $error_message,
        );
    }
}

}

new CNCRM_Field_Mappings();
?>
