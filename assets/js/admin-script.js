// Fetch Accee Token
jQuery(document).ready(function ($) {
    var crmConnected = false;
    var accessToken = '';

    // Function to fetch CRM access token via AJAX
    function fetchAccessTokenFromWordPress() {
        $.ajax({
            url: cncrm_admin_object.ajax_url,
            type: 'POST',
            data: {
                action: 'get_crm_access_token_ajax',
                nonce: cncrm_admin_object.cncrm_nonce
            },
            success: function(response) {
                if (response.success) {
                    accessToken = response.data.access_token;
                    crmConnected = true;
                    console.log('CRM Access Token:', accessToken);
                    enableFormSubmission(); // Enable form submission after CRM connection
                } else {
                    console.error('Failed to fetch CRM Access Token:', response.data.message);
                    crmConnected = false;
                    disableFormSubmission(); // Disable form submission if CRM connection fails
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                crmConnected = false;
                disableFormSubmission(); // Disable form submission if CRM connection fails
            }
        });
    }

    // Function to enable form submission after CRM connection
    function enableFormSubmission() {
        // Intercept WPForm submit button click
        $(document).on('click', '.wpforms-submit', function(e) {
            if (!crmConnected) {
                return; // Exit early if CRM is not connected
            }

            var $form = $(this).closest('form');
            var formId = $form.find('input[name="wpforms[id]"]').val(); // Adjust this selector based on your form structure - var formId = $form.find('input[name^="wpforms[settings][formId]"]').val();
            var formData = $form.serializeArray();

            // Fetch mappings for the form
            fetchFormMappings(formId, formData);
        });
    }

    // Function to fetch mappings for the form
    function fetchFormMappings(formId, formData) {
        if (!formId) return;

        $.ajax({
            url: cncrm_admin_object.ajax_url,
            type: 'POST',
            data: {
                action: 'fetch_field_mappings',
                form_id: formId,
                _ajax_nonce: cncrm_admin_object.cncrm_nonce
            },
            success: function(response) {
                if (response.success) {
                    var mappings = response.data.mappings;
                    var crmData = {};

                    // Compare and map form data to CRM data based on mappings
                    for (var key in mappings) {
                        var fieldId = key;
                        var fieldValue = formData.find(function(item) {
                            return item.name === mappings[key];
                        });

                        if (fieldValue) {
                            crmData[fieldId] = fieldValue.value;
                        }
                    }

                    // Send data to CRM via AJAX
                    sendToCRM(crmData);
                } else {
                    console.error('Failed to fetch field mappings:', response.data.message);
                    alert('Failed to fetch field mappings.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('An error occurred while fetching field mappings.');
            }
        });
    }

    // Function to send data to CRM
    function sendToCRM(crmData) {
        if (!accessToken) {
            console.error('CRM Access Token not found. Unable to send data to CRM.');
            return;
        }

        $.ajax({
            url: 'https://your-crm-api.com/v1/data', // Replace with your actual CRM API endpoint
            type: 'POST',
            headers: {
                'Authorization': 'Bearer ' + accessToken,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(crmData),
            success: function(response) {
                console.log('Data sent to CRM successfully:', response);
                alert('Data sent to CRM successfully!');
                // Optionally, perform additional actions after successful CRM data submission
            },
            error: function(xhr, status, error) {
                console.error('Error sending data to CRM:', status, error);
                alert('Failed to send data to CRM.');
                // Optionally, handle error case
            }
        });
    }

    // Fetch access token on page load
    fetchAccessTokenFromWordPress();

    // Refresh token button click handler (if needed)
    $('#refresh-token').click(function () {
        fetchAccessTokenFromWordPress();
    });

    // Disable form submission until CRM connection is established
    function disableFormSubmission() {
        $(document).off('click', '.wpforms-submit');
    }
});



jQuery(document).ready(function($) {
  // AJAX Request for Mapping Form Fields
  window.fetchFormFields = function(formId) {
      if (!formId) return;

      $.ajax({
          url: cncrm_admin_object.ajax_url,
          type: 'POST',
          data: {
              action: 'fetch_wpform_fields',
              form_id: formId,
              _ajax_nonce: cncrm_admin_object.cncrm_nonce
          },
          success: function(response) {
              if (response.success) {
                  $('#inside').html(response.data.html);
              } else {
                  $('#inside').html('<p>' + response.data.message + '</p>');
              }
          },
          error: function(xhr, status, error) {
              console.error('AJAX Error:', status, error);
              $('#inside').html('<p>An error occurred while fetching form fields.</p>');
          }
      });
  };

  // Trigger form fields fetch on form select change
  $('#wpforms_select').change(function() {
      var selectedFormId = $(this).val();
      fetchFormFields(selectedFormId);
  });

  // Save Mapping Button Click Handler
  $('#save-mapping').off('click').on('click', function() {
      var formId = $('#wpforms_select').val();
      var formName = $('#wpforms_select option:selected').text();
      var mappings = {};

      // Collect selected mappings
      $('#inside .form-field-mapping select').each(function() {
          var fieldId = $(this).attr('id').replace('field_mapping_', '');
          var fieldValue = $(this).val();
          mappings[fieldId] = fieldValue;
      });

      // AJAX request to save mappings
      $.ajax({
          url: cncrm_admin_object.ajax_url,
          type: 'POST',
          data: {
              action: 'save_field_mappings',
              form_id: formId,
              form_name: formName,
              mappings: mappings,
              _ajax_nonce: cncrm_admin_object.cncrm_nonce
          },
          success: function(response) {
              if (response.success) {
                  alert('Mappings saved successfully!');
              } else {
                  alert('Failed to save mappings: ' + response.data.message);
              }
          },
          error: function(xhr, status, error) {
              console.error('AJAX Error:', status, error);
              alert('Failed to save mappings.');
          }
      });
  });
});

// AJAX for WPForm submit button

jQuery(document).ready(function($) {
    // Intercept WPForm submit button click
    $(document).on('click', '.wpforms-submit', function(e) {
        e.preventDefault();

        var $form = $(this).closest('form');
        var formId = $form.find('input[name="wpforms[id]"]').val(); // Adjust this selector based on your form structure
        var formData = $form.serializeArray();

        console.log('Form ID:', formId);
        console.log('Form Data:', formData);

        // Fetch mappings for the form
        fetchFormMappings(formId, formData, $form);
    });

    // Function to fetch mappings for the form
    function fetchFormMappings(formId, formData, $form) {
        if (!formId) {
            console.error('No form ID found');
            return;
        }

        $.ajax({
            url: cncrm_admin_object.ajax_url,
            type: 'POST',
            data: {
                action: 'fetch_field_mappings',
                form_id: formId,
                _ajax_nonce: cncrm_admin_object.cncrm_nonce
            },
            success: function(response) {
                if (response.success) {
                    var mappings = response.data.mappings;
                    var crmData = {};

                    console.log('Mappings:', mappings);

                    // Compare and map form data to CRM data based on mappings
                    for (var key in mappings) {
                        var fieldId = key;
                        var fieldValue = formData.find(function(item) {
                            return item.name === mappings[key];
                        });

                        if (fieldValue) {
                            crmData[fieldId] = fieldValue.value;
                        }
                    }

                    console.log('CRM Data:', crmData);

                    // Send data to CRM via AJAX
                    sendToCRM(crmData, $form);
                } else {
                    console.error('Failed to fetch field mappings:', response.data.message);
                    alert('Failed to fetch field mappings.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('An error occurred while fetching field mappings.');
            }
        });
    }

    // Function to send data to CRM
    function sendToCRM(crmData, $form) {
        $.ajax({
            url: cncrm_admin_object.ajax_url,
            type: 'POST',
            data: {
                action: 'send_data_to_crm',
                crm_data: crmData,
                _ajax_nonce: cncrm_admin_object.cncrm_nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Data sent to CRM successfully!');
                    $form.submit(); // Continue form submission
                } else {
                    console.error('Failed to send data to CRM:', response.data.message);
                    alert('Failed to send data to CRM.');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('An error occurred while sending data to CRM.');
            }
        });
    }
});



// AJAX request to clear logs

    jQuery(document).ready(function ($) {
      // AJAX request to clear logs
      $('#clear-log-button').on('click', function(e) {
        e.preventDefault();
  
        $.ajax({
          url: cncrm_admin_object.ajax_url,
          method: 'POST',
          data: {
            action: 'wp_clear_logs',
            nonce: cncrm_admin_object.cncrm_nonce,
          },
          success: function(response) {
            if (response.success) {
              alert('Log file cleared successfully.');
            } else {
              alert('Error: ' + response.data);
            }
          },
          error: function() {
            alert('An error occurred while clearing the log file.');
          }
        });
      });
    });