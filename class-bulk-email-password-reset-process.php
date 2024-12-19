<?php
// prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// ensure WP_Background_Process is available
if (!class_exists('WP_Background_Process')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-background-process.php';
}

class Mass_Password_Reset_Process extends WP_Background_Process {
    protected $action = 'mass_password_reset';

    protected function task($user) {
        // ensure we have a valid email address
        if (!is_object($user) || !isset($user->user_email) || !is_email($user->user_email)) {
            mpwr_debug_log("Invalid user object or email: " . print_r($user, true));
            return false;
        }

        try {
            $email = $user->user_email;

            // send password reset for individual user
            $result = retrieve_password($email);
            
            if (!is_wp_error($result)) {
                mpwr_add_log_message("Email sent to: " . $email);
            } else {
                mpwr_add_log_message("Failed to send email to: " . $email . " - " . $result->get_error_message());
            }
        } catch (Exception $e) {
            mpwr_add_log_message("Exception processing email for: " . $email . " - " . $e->getMessage());
        }

        return false;
    }

    protected function complete() {
        parent::complete();
        
        // log completion
        mpwr_add_log_message('Mass password reset process COMPLETED');
        
        // optional: Send an admin notification
        wp_mail(
            get_option('admin_email'),
            'Mass Password Reset Completed',
            'The mass password reset process has finished.'
        );
    }
}
