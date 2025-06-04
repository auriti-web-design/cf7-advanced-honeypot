<?php if (!defined('ABSPATH')) exit;

/**
 * Template Name: CF7 Advanced Honeypot Settings Page
 */
?>

<div class="wrap cf7-honeypot-settings">
    <div class="settings-header">
        <h1><?php esc_html_e('CF7 Advanced Honeypot Settings', 'cf7-honeypot'); ?></h1>
        <p><?php esc_html_e('Configure advanced spam protection settings for your Contact Form 7 forms.', 'cf7-honeypot'); ?></p>
    </div>

    <?php settings_errors(); ?>

    <nav class="nav-tab-wrapper">
        <a href="#protection" class="nav-tab nav-tab-active" data-tab="protection">
            <span class="dashicons dashicons-shield"></span>
            <?php esc_html_e('Protection', 'cf7-honeypot'); ?>
        </a>
        <a href="#notification" class="nav-tab" data-tab="notification">
            <span class="dashicons dashicons-bell"></span>
            <?php esc_html_e('Notifications', 'cf7-honeypot'); ?>
        </a>
        <a href="#customization" class="nav-tab" data-tab="customization">
            <span class="dashicons dashicons-admin-customizer"></span>
            <?php esc_html_e('Customization', 'cf7-honeypot'); ?>
        </a>
    </nav>

    <form method="post" action="options.php" class="settings-form">
        <?php settings_fields('cf7_honeypot_settings'); ?>

        <!-- Protection Tab -->
        <div class="tab-content" id="protection">
            <div class="settings-section">
                <h2><?php esc_html_e('Protection Settings', 'cf7-honeypot'); ?></h2>
                <div class="settings-grid">
                    <!-- Auto Block IPs -->
                    <div class="settings-field">
                        <label class="toggle-switch">
                            <input type="checkbox" name="cf7_honeypot_settings[enable_auto_block]"
                                <?php checked(get_option('cf7_honeypot_settings')['enable_auto_block'] ?? false); ?>>
                            <span class="slider"></span>
                        </label>
                        <span class="setting-label"><?php esc_html_e('Auto-block IPs', 'cf7-honeypot'); ?></span>
                        <p class="setting-description"><?php esc_html_e('Automatically block IPs after repeated spam attempts', 'cf7-honeypot'); ?></p>
                    </div>

                    <!-- Block Threshold -->
                    <div class="settings-field">
                        <input type="number" name="cf7_honeypot_settings[block_threshold]"
                            value="<?php echo esc_attr(get_option('cf7_honeypot_settings')['block_threshold'] ?? '5'); ?>"
                            min="1" max="100">
                        <span class="setting-label"><?php esc_html_e('Block Threshold', 'cf7-honeypot'); ?></span>
                        <p class="setting-description"><?php esc_html_e('Number of attempts before blocking an IP', 'cf7-honeypot'); ?></p>
                    </div>

                    <!-- Block Duration -->
                    <div class="settings-field">
                        <input type="number" name="cf7_honeypot_settings[block_duration]"
                            value="<?php echo esc_attr(get_option('cf7_honeypot_settings')['block_duration'] ?? '24'); ?>"
                            min="1" max="720">
                        <span class="setting-label"><?php esc_html_e('Block Duration (hours)', 'cf7-honeypot'); ?></span>
                        <p class="setting-description"><?php esc_html_e('How long to block IPs (in hours)', 'cf7-honeypot'); ?></p>
                    </div>

                    <!-- Countries Blocking -->
                    <div class="settings-field full-width">
                        <label class="setting-label"><?php esc_html_e('Country Blocking', 'cf7-honeypot'); ?></label>
                        <select name="cf7_honeypot_settings[blocked_countries][]" multiple class="country-select" style="width: 100%; max-width: 100%;">
                            <?php
                            $countries = include(plugin_dir_path(dirname(__FILE__)) . 'includes/countries.php');
                            $blocked_countries = get_option('cf7_honeypot_settings')['blocked_countries'] ?? array();

                            foreach ($countries as $code => $name) {
                                printf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr($code),
                                    selected(in_array($code, $blocked_countries), true, false),
                                    esc_html($name)
                                );
                            }
                            ?>
                        </select>
                        <p class="setting-description"><?php esc_html_e('Select countries to block form submissions from', 'cf7-honeypot'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notification Tab -->
        <div class="tab-content" id="notification" style="display: none;">
            <div class="settings-section">
                <h2><?php esc_html_e('Notification Settings', 'cf7-honeypot'); ?></h2>
                <div class="settings-grid">
                    <div class="settings-field">
                        <label class="toggle-switch">
                            <input type="checkbox" name="cf7_honeypot_settings[admin_notifications]"
                                   <?php checked(get_option('cf7_honeypot_settings')['admin_notifications'] ?? false); ?>>
                            <span class="slider"></span>
                        </label>
                        <span class="setting-label"><?php esc_html_e('Admin Notifications', 'cf7-honeypot'); ?></span>
                        <p class="setting-description"><?php esc_html_e('Send email notifications for blocked attempts', 'cf7-honeypot'); ?></p>
                    </div>

                    <div class="settings-field">
                        <input type="number" name="cf7_honeypot_settings[notification_threshold]"
                               value="<?php echo esc_attr(get_option('cf7_honeypot_settings')['notification_threshold'] ?? '3'); ?>"
                               min="1" max="100">
                        <span class="setting-label"><?php esc_html_e('Notification Threshold', 'cf7-honeypot'); ?></span>
                        <p class="setting-description"><?php esc_html_e('Minimum attempts before sending notification', 'cf7-honeypot'); ?></p>
                    </div>

                    <div class="settings-field full-width">
                        <textarea name="cf7_honeypot_settings[additional_recipients]" rows="4"
                                  placeholder="<?php esc_attr_e('Enter email addresses, one per line', 'cf7-honeypot'); ?>"><?php
                            echo esc_textarea(get_option('cf7_honeypot_settings')['additional_recipients'] ?? '');
                        ?></textarea>
                        <span class="setting-label"><?php esc_html_e('Additional Recipients', 'cf7-honeypot'); ?></span>
                        <p class="setting-description"><?php esc_html_e('Additional email addresses to notify (one per line)', 'cf7-honeypot'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customization Tab -->
        <div class="tab-content" id="customization" style="display: none;">
            <div class="settings-section">
                <h2><?php esc_html_e('Customization Settings', 'cf7-honeypot'); ?></h2>
                <div class="settings-grid">
                    <div class="settings-field full-width">
                        <div id="custom-questions-container">
                            <?php
                            $custom_questions = get_option('cf7_honeypot_settings')['custom_questions'] ?? [];
                            foreach ($custom_questions as $index => $question): ?>
                                <div class="question-row">
                                    <input type="text"
                                           name="cf7_honeypot_settings[custom_questions][<?php echo $index; ?>][question]"
                                           value="<?php echo esc_attr($question['question']); ?>"
                                           placeholder="<?php esc_attr_e('Question', 'cf7-honeypot'); ?>">
                                    <input type="text"
                                           name="cf7_honeypot_settings[custom_questions][<?php echo $index; ?>][answer]"
                                           value="<?php echo esc_attr($question['answer']); ?>"
                                           placeholder="<?php esc_attr_e('Answer', 'cf7-honeypot'); ?>">
                                    <button type="button" class="button remove-question">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" id="add-question" class="button button-secondary">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e('Add Question', 'cf7-honeypot'); ?>
                        </button>
                    </div>

                    <div class="settings-field full-width">
                        <input type="text" name="cf7_honeypot_settings[custom_error_message]"
                               value="<?php echo esc_attr(get_option('cf7_honeypot_settings')['custom_error_message'] ?? ''); ?>"
                               placeholder="<?php esc_attr_e('Enter custom error message', 'cf7-honeypot'); ?>">
                        <span class="setting-label"><?php esc_html_e('Custom Error Message', 'cf7-honeypot'); ?></span>
                        <p class="setting-description"><?php esc_html_e('Message shown when spam is detected', 'cf7-honeypot'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <?php submit_button(__('Save Changes', 'cf7-honeypot'), 'primary large'); ?>
    </form>
</div>
