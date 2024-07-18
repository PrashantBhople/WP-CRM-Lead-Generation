<?php

function cncrm_settings_page() {
    try {
        // Log entry into the method
        error_log('Entered cncrm_settings_page method.');

        // Check if WPForms is active
        if (!post_type_exists('wpforms')) {
            throw new Exception('WPForms post type does not exist. Ensure WPForms is installed and activated.');
        }

        // Log before retrieving WPForms
        error_log('WPForms post type exists.');

        // Retrieve WPForms
        $forms = get_posts(array(
            'post_type' => 'wpforms',
            'numberposts' => -1
        ));

        // Log after retrieving WPForms
        error_log('Retrieved forms: ' . print_r($forms, true));

        // Check if forms are retrieved
        if (empty($forms)) {
            throw new Exception('No WPForms found. Please create a form first.');
        }

        // Proceed with rendering the settings page
        ?>
        <div class="wrap crmIntegationSettings">
            <h1 class="ttl_crmSettings"><?php echo esc_html__('CRM Integration Settings', 'cncrm'); ?></h1>
            <hr class="divide">
            <div class="wp-formSelect">
                <h3><?php echo esc_html__('Select Form', 'cncrm'); ?></h3>
            </div>
            <div class="wp-select">
                <select id="wpforms_select" name="cncrm_selected_form" onchange="fetchFormFields(this.value)" class="select_wpform_dropdown">
                    <option value=""><?php echo esc_html__('Select Form', 'cncrm'); ?></option>
                    <?php
                    foreach ($forms as $form) {
                        echo '<option value="' . esc_attr($form->ID) . '">' . esc_html($form->post_title) . '</option>';
                    }
                    ?>
                </select>
                <input type="hidden" name="wp-ajax-nonce" id="wp-ajax-nonce" value="<?php echo esc_attr(wp_create_nonce('wp-ajax-nonce')); ?>" />
            </div>
            <div class="wrap gs-form fieldMappingContainer">
                <div class="wp-parts">
                    <div class="card" id="wpform-gs">
                        <form method="post" id="cncrm-settings-form">
                            <div id="inside"></div> <!-- Ensure this element exists -->
                            <button type="button" class="button button-primary btn_saveMapping" id="save-mapping"><?php esc_html_e('Save Mapping', 'cncrm'); ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
        // Log successful execution
        error_log('cncrm_settings_page method executed successfully.');

    } catch (Exception $e) {
        // Log the error message
        error_log('Error in cncrm_settings_page: ' . $e->getMessage());
        echo '<div class="error"><p>' . esc_html($e->getMessage()) . '</p></div>';
    }
}


add_action('admin_enqueue_scripts', 'cncrm_admin_enqueue_scripts');
function cncrm_admin_enqueue_scripts() {
    wp_enqueue_script('admin-script', plugins_url('assets/js/admin-script.js', __FILE__), array('jquery'), '1.0', true);
    wp_localize_script('admin-script', 'cncrm_settings_data', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'cncrm_nonce' => wp_create_nonce('cncrm_nonce')
    ));
}
?>
