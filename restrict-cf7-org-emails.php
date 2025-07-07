<?php
/*
Plugin Name: Restrict CF7 to Organization Emails
Description: Validates Contact Form 7 submissions to allow only organization emails and redirects after successful submission.
Version: 1.0
Author: Over and All Solutions
Authr URI: https://overandallsolutions.com
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: restrict-cf7-org-emails
Domain Path: /languages
Requires at least: 5.0 
Tested up to: 6.5
Requires PHP: 7.0
GitHub Plugin URI:https://github.com/overandall/restrictcontactformorgemail.git
GitHub Plugin URI: overandall/restrictcontactformorgemail
GitHub Branch: main
*/

if (!defined('ABSPATH')) exit; // Protect direct access

// ===========================================
// 1. Admin Settings Page
// ===========================================
add_action('admin_menu', function() {
    add_options_page(
        'Restrict CF7 Org Emails',
        'Restrict CF7 Org Emails',
        'manage_options',
        'restrict-cf7-org-emails',
        'rcf7_org_emails_settings_page'
    );
});

function rcf7_org_emails_settings_page() {
    ?>
    <div class="wrap">
        <h1>Restrict CF7 Organization Emails</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('rcf7_org_emails_settings_group');
            do_settings_sections('rcf7_org_emails_settings_group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Apply to all forms</th>
                    <td>
                        <input type="checkbox" name="rcf7_apply_globally" value="1" <?php checked(1, get_option('rcf7_apply_globally', 0)); ?> />
                        <label>Yes, apply to all CF7 forms</label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Form IDs (comma separated)</th>
                    <td><input type="text" name="rcf7_form_ids" value="<?php echo esc_attr(get_option('rcf7_form_ids', '')); ?>" size="50"/></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Email Field Name</th>
                    <td><input type="text" name="rcf7_email_field" value="<?php echo esc_attr(get_option('rcf7_email_field', 'corporate-email')); ?>" size="50"/></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Blocked Personal Domains</th>
                    <td><textarea name="rcf7_blocked_domains" rows="5" cols="50"><?php echo esc_textarea(get_option('rcf7_blocked_domains', 'gmail.com,yahoo.com,hotmail.com,outlook.com,live.com,aol.com,icloud.com,protonmail.com,rediffmail.com')); ?></textarea></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', function() {
    register_setting('rcf7_org_emails_settings_group', 'rcf7_apply_globally');
    register_setting('rcf7_org_emails_settings_group', 'rcf7_form_ids');
    register_setting('rcf7_org_emails_settings_group', 'rcf7_email_field');
    register_setting('rcf7_org_emails_settings_group', 'rcf7_blocked_domains');
});

// ===========================================
// 2. Server-side Validation
// ===========================================
add_filter('wpcf7_validate_email*', 'rcf7_org_email_validation', 20, 2);
add_filter('wpcf7_validate_email', 'rcf7_org_email_validation', 20, 2);

function rcf7_org_email_validation($result, $tag) {
    $apply_globally = get_option('rcf7_apply_globally', 0);
    $form_ids = array_map('trim', explode(',', get_option('rcf7_form_ids', '')));
    $field_name = trim(get_option('rcf7_email_field', 'corporate-email'));
    $blocked_domains = array_map('strtolower', array_map('trim', explode(',', get_option('rcf7_blocked_domains', ''))));

    if (!isset($_POST['_wpcf7'])) return $result;
    $current_form_id = sanitize_text_field($_POST['_wpcf7']);

    if (!$apply_globally && !in_array($current_form_id, $form_ids)) {
        return $result;
    }

    if ($tag['name'] !== $field_name) {
        return $result;
    }

    $email = isset($_POST[$field_name]) ? sanitize_email($_POST[$field_name]) : '';
    $domain = strtolower(trim(substr(strrchr($email, "@"), 1)));

    if (in_array($domain, $blocked_domains)) {
        $result->invalidate($tag, "Please use your organization email address. Personal emails are not allowed.");
    }

    return $result;
}

// ===========================================
// 3. Client-side JS Validation
// ===========================================
add_action('wp_footer', function() {
    $apply_globally = get_option('rcf7_apply_globally', 0);
    $form_ids = array_map('trim', explode(',', get_option('rcf7_form_ids', '')));
    $field_name = esc_js(get_option('rcf7_email_field', 'corporate-email'));
    $blocked_domains = array_map('strtolower', array_map('trim', explode(',', get_option('rcf7_blocked_domains', ''))));
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var applyGlobally = <?php echo $apply_globally ? 'true' : 'false'; ?>;
        var validFormIds = <?php echo json_encode($form_ids); ?>;
        var blockedDomains = <?php echo json_encode($blocked_domains); ?>;
        var fieldName = "<?php echo $field_name; ?>";

        document.querySelectorAll('.wpcf7 form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var formIdInput = form.querySelector('input[name="_wpcf7"]');
                if (!formIdInput) return;

                var formId = formIdInput.value;
                if (!applyGlobally && !validFormIds.includes(formId)) return;

                var emailInput = form.querySelector('input[name="'+fieldName+'"]');
                if (!emailInput) return;

                var email = emailInput.value.trim();
                var domain = email.split('@')[1];
                if (domain && blockedDomains.includes(domain.toLowerCase())) {
                    e.preventDefault();
                    alert('Please use your organization email address. Personal emails are not allowed.');
                    emailInput.focus();
                }
            });
        });
    });
    </script>
    <?php
});