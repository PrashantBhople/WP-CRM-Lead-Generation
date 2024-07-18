<?php
/**
 * Class WPForms_Integration
 *
 * Service class for WPForms CRM Connector.
 */
class WPForms_Integration {

    /**
     * Register hooks for WPForms CRM Integration.
     */
    public function __construct() {
        add_action('wpforms_process_complete', array($this, 'process_wpforms_entry'), 10, 4);
    }

    /**
     * Process WPForms entry to integrate with CRM.
     *
     * @param array $fields       Submitted form fields.
     * @param array $entry        Form entry data.
     * @param array $form_data    Form settings and data.
     * @param int   $entry_id     Entry ID.
     */
    public function process_wpforms_entry($fields, $entry, $form_data, $entry_id) {
        // Your CRM integration logic here
        $form_id = $entry['form_id'];
        $form_title = $form_data['settings']['form_title'];

        // Example: Send form data to CRM
        $this->send_to_crm($fields, $entry, $form_data, $entry_id);
    }

/**
 * Example method to send form data to CRM.
 *
 * @param array $fields    Array of field values submitted in the form.
 * @param array $entry     Array of entry values submitted in the form.
 * @param array $form_data Array of form data.
 * @param int   $entry_id  Entry ID.
 */
private function send_to_crm($fields, $entry, $form_data, $entry_id) {
    // Example CRM integration logic

    // Access relevant form data
    $form_id = $entry['form_id'];
    $form_title = $form_data['settings']['form_title'];

    // Extract fields and prepare CRM data
    $crm_data = array(
        'form_id' => $form_id,
        'form_title' => $form_title,
        'entry_id' => $entry_id,
        'fields' => $fields, // All form fields
        'entry' => $entry,   // Raw entry data
    );

    // Example: Send data to CRM (replace with your actual CRM integration logic)
    $response = $this->send_data_to_crm_api($crm_data);

    // Check CRM response and handle accordingly
    if ($response['success']) {
        // Log CRM integration success
        $this->log_message('CRM integration successful.');
    } else {
        // Log CRM integration failure
        $this->log_message('CRM integration failed: ' . $response['error']);
    }
}

/**
 * Example method to log messages.
 *
 * @param string $message Message to log.
 */
private function log_message($message) {
    // Example: Log message to a file or database
    error_log('[CRM Integration] ' . $message);
}

/**
 * Example method to send data to CRM API.
 *
 * @param array $data Data to send to CRM.
 * @return array CRM API response.
 */
private function send_data_to_crm_api($data) {
    // Example: Replace with actual CRM API integration logic
    // Simulate sending data and receiving response
    $response = array(
        'success' => true, // Replace with actual success response check
        'error' => '',     // Replace with actual error response check
    );

    return $response;
}
}