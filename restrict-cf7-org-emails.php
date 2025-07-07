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

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// ===========================================
// 1. Server-side validation for Contact Form 7
// ===========================================
add_filter('wpcf7_validate_email*', 'rcf7_org_email_validation', 20, 2);
add_filter('wpcf7_validate_email', 'rcf7_org_email_validation', 20, 2);

function rcf7_org_email_validation($result, $tag) {
    // Target specific form ID
    if (isset($_POST['_wpcf7']) && $_POST['_wpcf7'] != '88c279d') {
        return $result;
    }

    $tag_name = $tag['name'];
    if ($tag_name !== 'corporate-email') {
        return $result;
    }

    $email = isset($_POST[$tag_name]) ? sanitize_email($_POST[$tag_name]) : '';

    $personal_domains = array(
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
        'live.com', 'aol.com', 'icloud.com', 'protonmail.com', 'rediffmail.com'
    );

    $domain = substr(strrchr($email, "@"), 1);

    if (in_array(strtolower($domain), $personal_domains)) {
        $result->invalidate($tag, "Please use your organization email address. Personal emails are not allowed.");
    }

    return $result;
}

// ===========================================
// 2. JavaScript injection for client-side UX
// ===========================================
add_action('wp_footer', function() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var forms = document.querySelectorAll('.wpcf7 form');

        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var emailInput = form.querySelector('input[name="corporate-email"]');
                if (!emailInput) return;

                var email = emailInput.value.trim();
                var personalDomains = [
                    'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
                    'live.com', 'aol.com', 'icloud.com', 'protonmail.com', 'rediffmail.com'
                ];

                var domain = email.split('@')[1];
                if (domain && personalDomains.includes(domain.toLowerCase())) {
                    e.preventDefault();
                    alert('Please use your organization email address (no personal emails).');
                    emailInput.focus();
                }
            });
        });
    });

    // Redirect after successful form submission
    document.addEventListener('wpcf7mailsent', function(event) {
        if (event.detail.contactFormId == '88c279d') {
            window.location.href = 'email.com';
        }
    });
    </script>
    <?php
});
