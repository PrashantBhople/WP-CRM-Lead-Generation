<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function cncrm_render_integration_ui() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Fetch the CRM URL from the options
    $crm_url = get_option('cncrm_crm_url', ''); // Default to an empty string if not set

    ?>
    <div class="wrap">
        <div class="logo_header">
        <img src="<?php echo CNCRM_URL; ?>assets/img/crmLogo.webp" class="logo-icon">
        </div>
        <!-- <h1 class="ttl_heading">CN CRM Integration</h1> -->
        <div class="gs-parts-wpform">
            <div class="card-wp">
                <input type="hidden" name="redirect_auth_wpforms" id="redirect_auth_wpforms"
                       value="<?php echo (isset($header)) ? esc_attr($header) : ''; ?>">
                <span class="wpforms-setting-field log-setting">
<!-- We can add logo here -->
                    <?php if (empty(get_option('wpform_gs_token'))) { ?>
                        <div class="wpform-gs-alert-kk" id="google-drive-msg">
                            <p class="wpform-gs-alert-heading">
                                <?php echo esc_html__('Authenticate with CRM URL and follow these steps:', 'cncrm'); ?>
                            </p>
                            <ol class="wpform-gs-alert-steps">
                                <li><?php echo esc_html__('Enter the data in the input box.', 'cncrm'); ?></li>
                                <li><?php echo esc_html__('Click on the "Send Data" button.', 'cncrm'); ?></li>
                                <li><?php echo esc_html__('You will be redirected to the CRM.', 'cncrm'); ?></li>
                            </ol>
                        </div>
                    <?php } ?>
                </span>
            </div>
        </div>

        <form method="post" action="<?php echo esc_url($crm_url); ?>">
            <div class="form-field-mapping crmUI">
                <label for="input_data" class="crmURL"><?php echo esc_html__('CRM URL:', 'cncrm'); ?></label>
                <input type="text" name="input_data" id="input_data" required>
            </div>
            <div class="form-field-mapping crmUIBtn">
                <input type="submit" value="<?php echo esc_attr__('Send Data', 'cncrm'); ?>" class="button-primary-submit">
            </div>
        </form>
    </div>
    <?php
}
?>
