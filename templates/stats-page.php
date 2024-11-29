<?php
/**
 * Template Name: CF7 Advanced Honeypot Statistics Page - Enhanced
 * Version: 1.2.0
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <div class="cf7-honeypot-stats">
        <!-- Page Header -->
        <div class="stats-header">
            <h1><?php esc_html_e('Anti-Spam Protection: Enhanced Statistics and Reports', 'cf7-honeypot'); ?></h1>
            <p><?php esc_html_e('Monitor spam attempts and analyze your website security with detailed insights.', 'cf7-honeypot'); ?>
            </p>
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

        <!-- Advanced Statistics Dashboard -->
        <div class="advanced-stats-section">
            <h2><?php esc_html_e('Detailed Analysis', 'cf7-honeypot'); ?></h2>
            <div class="stats-grid">
                <?php if (!empty($summary_stats)): ?>
                    <?php
                    $latest_stats = reset($summary_stats); // Get most recent day's stats
                    if ($latest_stats):
                        ?>
                        <div class="detailed-stat-card">
                            <h4><?php esc_html_e('Today\'s Overview', 'cf7-honeypot'); ?></h4>
                            <ul class="stat-list">
                                <li>
                                    <span class="stat-label"><?php esc_html_e('Unique IPs:', 'cf7-honeypot'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($latest_stats->unique_ips); ?></span>
                                </li>
                                <li>
                                    <span class="stat-label"><?php esc_html_e('Unique Browsers:', 'cf7-honeypot'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($latest_stats->unique_browsers); ?></span>
                                </li>
                                <li>
                                    <span class="stat-label"><?php esc_html_e('Forms Affected:', 'cf7-honeypot'); ?></span>
                                    <span class="stat-value"><?php echo esc_html($latest_stats->forms_affected); ?></span>
                                </li>
                                <li>
                                    <span
                                        class="stat-label"><?php esc_html_e('Honeypot Fields Triggered:', 'cf7-honeypot'); ?></span>
                                    <span
                                        class="stat-value"><?php echo esc_html($latest_stats->unique_fields_triggered); ?></span>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Attempts Table with Enhanced Information -->
        <div class="attempts-table">
            <h2><?php esc_html_e('Recent Spam Attempts', 'cf7-honeypot'); ?></h2>
            <?php if (!empty($recent_attempts)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="12%"><?php esc_html_e('Date/Time', 'cf7-honeypot'); ?></th>
                            <th width="15%"><?php esc_html_e('Form', 'cf7-honeypot'); ?></th>
                            <th width="12%"><?php esc_html_e('IP Address', 'cf7-honeypot'); ?></th>
                            <th width="15%"><?php esc_html_e('Email', 'cf7-honeypot'); ?></th>
                            <th width="12%"><?php esc_html_e('Triggered Field', 'cf7-honeypot'); ?></th>
                            <th width="10%"><?php esc_html_e('Status', 'cf7-honeypot'); ?></th>
                            <th width="24%"><?php esc_html_e('Additional Info', 'cf7-honeypot'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_attempts as $attempt): ?>
                            <tr>
                                <td><?php echo esc_html(wp_date('d/m/Y H:i:s', strtotime($attempt->created_at))); ?></td>
                                <td><?php echo esc_html($attempt->form_title ?: 'Form #' . $attempt->form_id); ?></td>
                                <td><?php echo esc_html($attempt->ip_address); ?></td>
                                <td><?php echo esc_html($attempt->email ?: 'N/A'); ?></td>
                                <td><?php echo esc_html($attempt->triggered_field ?: 'Unknown'); ?></td>
                                <td>
                                    <span
                                        class="status-badge risk-<?php echo esc_attr(get_risk_level($attempt->attempts_count)); ?>">
                                        <?php echo esc_html(get_risk_label(get_risk_level($attempt->attempts_count))); ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="button button-small show-details"
                                        data-ua="<?php echo esc_attr($attempt->user_agent); ?>"
                                        data-referrer="<?php echo esc_attr($attempt->referrer_url); ?>">
                                        <?php esc_html_e('Show Details', 'cf7-honeypot'); ?>
                                    </button>
                                </td>
                            </tr>
                            <tr class="details-row hidden">
                                <td colspan="7">
                                    <div class="details-content">
                                        <p><strong><?php esc_html_e('Browser:', 'cf7-honeypot'); ?></strong>
                                            <?php echo esc_html($attempt->user_agent ?: 'Unknown'); ?></p>
                                        <p><strong><?php esc_html_e('Referrer:', 'cf7-honeypot'); ?></strong>
                                            <?php echo esc_html($attempt->referrer_url ?: 'Direct Access'); ?></p>
                                        <p><strong><?php esc_html_e('Total Attempts:', 'cf7-honeypot'); ?></strong>
                                            <?php echo esc_html($attempt->attempts_count); ?></p>
                                    </div>
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

<!-- Inline Script for Toggling Details -->
<script type="text/javascript">
    jQuery(document).ready(function ($) {
        $('.show-details').on('click', function () {
            $(this).closest('tr').next('.details-row').toggleClass('hidden');
        });
    });
</script>

<!-- Additional Inline Styles -->
<style>
    .details-row.hidden {
        display: none;
    }

    .details-content {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 4px;
        margin: 10px;
    }

    .detailed-stat-card {
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        margin-bottom: 20px;
    }

    .stat-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .stat-list li {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }

    .stat-list li:last-child {
        border-bottom: none;
    }

    .show-details {
        background: #f8f9fa;
        border-color: #ddd;
    }

    .show-details:hover {
        background: #e9ecef;
    }
</style>