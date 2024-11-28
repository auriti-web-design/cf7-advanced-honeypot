<?php
/**
 * Template Name: CF7 Advanced Honeypot Statistics Page
 *
 * Displays statistics for the CF7 Advanced Honeypot plugin.
 * Includes:
 * - Statistics overview (24h, 7 days, 30 days, total)
 * - Log cleanup system
 * - Detailed spam attempts visualization
 *
 * @package CF7_Advanced_Honeypot
 * @version 1.0.0
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

/**
 * Handle log cleanup request
 * Verify nonce and clean logs based on selected period
 */
if (isset($_POST['action']) && $_POST['action'] === 'clear_logs' && check_admin_referer('cf7_honeypot_clear_logs')) {
    $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : '30';
    CF7_Advanced_Honeypot::get_instance()->cleanup_old_logs($period);
    echo '<div class="notice notice-success"><p>' . esc_html__('Logs successfully deleted!', 'cf7-honeypot') . '</p></div>';
}

// Define stats table
$stats_table = $wpdb->prefix . 'cf7_honeypot_stats';

// Get statistics for different time periods
$last_24h = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$stats_table}
    WHERE honeypot_triggered = 1
    AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
));

$last_7d = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$stats_table}
    WHERE honeypot_triggered = 1
    AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"
));

$last_30d = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$stats_table}
    WHERE honeypot_triggered = 1
    AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
));

$total_attempts = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$stats_table}
    WHERE honeypot_triggered = 1"
));

// Get recent spam attempts details
$recent_attempts = $wpdb->get_results(
    "SELECT s.*,
        p.post_title as form_title,
        (SELECT COUNT(*)
         FROM {$stats_table}
         WHERE ip_address = s.ip_address
         AND honeypot_triggered = 1) as attempts_count,
        (SELECT GROUP_CONCAT(DISTINCT form_id)
         FROM {$stats_table}
         WHERE ip_address = s.ip_address
         AND honeypot_triggered = 1) as targeted_forms,
        DATE_FORMAT(s.created_at, '%Y-%m-%d') as attempt_date
    FROM {$stats_table} s
    LEFT JOIN {$wpdb->posts} p ON s.form_id = p.ID
    WHERE s.honeypot_triggered = 1
    ORDER BY s.created_at DESC
    LIMIT 10"
);

/**
 * Helper function to determine risk level
 */
function get_risk_level($attempts_count)
{
    if ($attempts_count > 5)
        return 'high';
    if ($attempts_count > 2)
        return 'medium';
    return 'low';
}

/**
 * Helper function to get risk level label
 */
function get_risk_label($risk_level)
{
    switch ($risk_level) {
        case 'high':
            return __('High Risk', 'cf7-honeypot');
        case 'medium':
            return __('Medium Risk', 'cf7-honeypot');
        default:
            return __('Low Risk', 'cf7-honeypot');
    }
}
?>

<div class="wrap">
    <div class="cf7-honeypot-stats">
        <!-- Page Header -->
        <div class="stats-header">
            <h1><?php esc_html_e('Anti-Spam Protection: Statistics and Reports', 'cf7-honeypot'); ?></h1>
            <p><?php esc_html_e('Monitor spam attempts and analyze your website security.', 'cf7-honeypot'); ?></p>
        </div>

        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <h3><?php esc_html_e('Last 24 Hours', 'cf7-honeypot'); ?></h3>
                <div class="stat-number"><?php echo esc_html($last_24h); ?></div>
                <div class="stat-label"><?php esc_html_e('blocked attempts', 'cf7-honeypot'); ?></div>
            </div>

            <div class="stat-card">
                <h3><?php esc_html_e('Last 7 Days', 'cf7-honeypot'); ?></h3>
                <div class="stat-number"><?php echo esc_html($last_7d); ?></div>
                <div class="stat-label"><?php esc_html_e('blocked attempts', 'cf7-honeypot'); ?></div>
            </div>

            <div class="stat-card">
                <h3><?php esc_html_e('Last 30 Days', 'cf7-honeypot'); ?></h3>
                <div class="stat-number"><?php echo esc_html($last_30d); ?></div>
                <div class="stat-label"><?php esc_html_e('blocked attempts', 'cf7-honeypot'); ?></div>
            </div>

            <div class="stat-card">
                <h3><?php esc_html_e('Total', 'cf7-honeypot'); ?></h3>
                <div class="stat-number"><?php echo esc_html($total_attempts); ?></div>
                <div class="stat-label"><?php esc_html_e('blocked attempts', 'cf7-honeypot'); ?></div>
            </div>
        </div>

        <!-- Log Cleanup Section -->
        <div class="cleanup-section">
            <h2><?php esc_html_e('Log Management', 'cf7-honeypot'); ?></h2>
            <div class="cleanup-options">
                <form method="post" action="">
                    <?php wp_nonce_field('cf7_honeypot_clear_logs'); ?>
                    <input type="hidden" name="action" value="clear_logs">

                    <button type="submit" name="period" value="1" class="cleanup-button"
                        onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete the last 24 hours logs?', 'cf7-honeypot'); ?>');">
                        <?php esc_html_e('Delete last 24 hours logs', 'cf7-honeypot'); ?>
                    </button>

                    <button type="submit" name="period" value="7" class="cleanup-button"
                        onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete the last 7 days logs?', 'cf7-honeypot'); ?>');">
                        <?php esc_html_e('Delete last 7 days logs', 'cf7-honeypot'); ?>
                    </button>

                    <button type="submit" name="period" value="30" class="cleanup-button"
                        onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete the last 30 days logs?', 'cf7-honeypot'); ?>');">
                        <?php esc_html_e('Delete last 30 days logs', 'cf7-honeypot'); ?>
                    </button>

                    <button type="submit" name="period" value="all" class="cleanup-button danger"
                        onclick="return confirm('<?php esc_attr_e('WARNING: You are about to delete all logs. This action cannot be undone. Do you want to continue?', 'cf7-honeypot'); ?>');">
                        <?php esc_html_e('Delete all logs', 'cf7-honeypot'); ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Spam Attempts Table -->
        <div class="attempts-table">
            <h2><?php esc_html_e('Recent Spam Attempts', 'cf7-honeypot'); ?></h2>
            <?php if (!empty($recent_attempts)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="15%"><?php esc_html_e('Date/Time', 'cf7-honeypot'); ?></th>
                            <th width="20%"><?php esc_html_e('Form', 'cf7-honeypot'); ?></th>
                            <th width="15%"><?php esc_html_e('IP Address', 'cf7-honeypot'); ?></th>
                            <th width="20%"><?php esc_html_e('Email', 'cf7-honeypot'); ?></th>
                            <th width="15%"><?php esc_html_e('Total Attempts', 'cf7-honeypot'); ?></th>
                            <th width="15%"><?php esc_html_e('Status', 'cf7-honeypot'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_attempts as $attempt): ?>
                            <tr>
                                <td><?php echo esc_html(wp_date('d/m/Y H:i:s', strtotime($attempt->created_at))); ?></td>
                                <td><?php echo esc_html($attempt->form_title ?: 'Form #' . $attempt->form_id); ?></td>
                                <td><?php echo esc_html($attempt->ip_address); ?></td>
                                <td><?php echo esc_html($attempt->email ?: 'N/A'); ?></td>
                                <td><span class="attempt-count"><?php echo esc_html($attempt->attempts_count); ?></span></td>
                                <td>
                                    <span
                                        class="status-badge risk-<?php echo esc_attr(get_risk_level($attempt->attempts_count)); ?>">
                                        <?php echo esc_html(get_risk_label(get_risk_level($attempt->attempts_count))); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-attempts"><?php esc_html_e('No spam attempts detected so far. 🎉', 'cf7-honeypot'); ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>