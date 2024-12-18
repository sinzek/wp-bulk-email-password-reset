<?php
/*
Plugin Name: Bulk Email Password Reset
Description: Allows mass sending of password reset emails with real-time logging. Utilizes https://github.com/deliciousbrains/wp-background-processing
Version: 1.5
Author: Chase Brock
Author URI: https://github.com/sinzek/wp-bulk-email-password-reset
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: bulk-email-password-reset
*/

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Include the background processing class
require_once plugin_dir_path(__FILE__) . 'class-bulk-email-password-reset-process.php';

// Transient to store log messages
function mpwr_add_log_message($message) {
    $logs = get_transient('mpwr_reset_logs') ?: [];
    $logs[] = date('[H:i:s] ') . $message;
    
    // Limit log size to prevent memory issues
    $logs = array_slice($logs, -100);
    
    set_transient('mpwr_reset_logs', $logs, DAY_IN_SECONDS);
}

// Debug logging function
function mpwr_debug_log($message) {
    error_log('MASS PASSWORD RESET: ' . $message);
    mpwr_add_log_message($message);
}

// Initialize the plugin
function mpwr_init() {
    global $mass_reset_processor;
    $mass_reset_processor = new Mass_Password_Reset_Process();
}
add_action('plugins_loaded', 'mpwr_init');

// Add admin menu
function mpwr_add_admin_menu() {
    add_management_page(
        'Mass Password Reset', 
        'Mass Password Reset', 
        'manage_options', 
        'mass-password-reset', 
        'mpwr_display_reset_page'
    );
}
add_action('admin_menu', 'mpwr_add_admin_menu');

// AJAX handler to fetch logs
function mpwr_fetch_logs() {
    // Verify nonce and user capabilities
    check_ajax_referer('mpwr_fetch_logs', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
    }
    
    $logs = get_transient('mpwr_reset_logs') ?: [];
    
    wp_send_json_success($logs);
}
add_action('wp_ajax_mpwr_fetch_logs', 'mpwr_fetch_logs');

// Admin page handler
// Admin page handler
function mpwr_display_reset_page() {
    global $mass_reset_processor;

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle form submission
    if (isset($_POST['confirm_mass_reset'])) {
        // Verify nonce for security
        check_admin_referer('mass_password_reset_action');

        // Clear previous logs
        delete_transient('mpwr_reset_logs');
        mpwr_debug_log('Mass password reset process started');

        // Get all users with valid emails
        $users = get_users([
            'fields' => ['ID', 'user_email'],
            'number' => '', // Retrieve all users
        ]);
        
        // Track total users
        $total_users = count($users);
        mpwr_debug_log("Total users to process: {$total_users}");
        
        // Dispatch emails to background processor
        $processed = 0;
        foreach ($users as $index => $user) {
            // Validate email before queueing
            if (is_email($user->user_email)) {
                $mass_reset_processor->push_to_queue($user);
                $processed++;
                
                // Log progress every 50 users
                if ($processed % 50 === 0) {
                    mpwr_debug_log("Queued {$processed} users");
                }
            } else {
                mpwr_debug_log("Skipping invalid email for user ID: {$user->ID}");
            }
        }

        // Dispatch the queue
        $mass_reset_processor->save()->dispatch();
        
        mpwr_debug_log("Queued {$processed} out of {$total_users} users");
        
        // Show success message
        echo '<div class="notice notice-success"><p>Password reset emails are being processed.</p></div>';
    }

    // Enqueue scripts for live logging
    wp_enqueue_script('mpwr-logs', plugin_dir_url(__FILE__) . 'logs.js', ['jquery'], '1.0', true);
    wp_localize_script('mpwr-logs', 'mpwrAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mpwr_fetch_logs')
    ]);

    // Confirmation page HTML
    ?>
    <div class="wrap">
        <h1>Mass Password Reset</h1>
        <form method="post">
            <?php wp_nonce_field('mass_password_reset_action'); ?>
            <p>This will send password reset emails to ALL users. Are you sure?</p>
            <input type="submit" name="confirm_mass_reset" class="button button-primary" value="Confirm Mass Password Reset">
        </form>

        <h2>Processing Log</h2>
        <div id="mpwr-log-container" style="
            width: 100%; 
            height: 300px; 
            border: 1px solid #ddd; 
            padding: 10px; 
            overflow-y: scroll; 
            background: #f8f8f8;
            font-family: monospace;
        "></div>
    </div>
    <?php
}