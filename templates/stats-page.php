<?php
/**
 * Template Name: CF7 Advanced Honeypot Statistics Page
 * Description: Displays comprehensive statistics about spam attempts and form submissions
 * Version: 1.3.1
 * Author: Juan Camilo Auriti
 *
 * This template renders the main statistics dashboard for the CF7 Advanced Honeypot plugin.
 * It shows various metrics including:
 * - Recent spam attempts
 * - Time-based statistics (24h, 7d, 30d)
 * - Detailed analysis of spam patterns
 * - Log management tools
 */

// Prevent direct file access for security
if (!defined('ABSPATH')) {
    exit;
}

// Initialize pagination parameters
$items_per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20; // Default 20 items per page
$current_page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Get total number of spam attempts for pagination
$total_items = $wpdb->get_var("
    SELECT COUNT(*)
    FROM {$stats_table}
    WHERE honeypot_triggered = 1
");
$total_pages = ceil($total_items / $items_per_page);

// Fetch paginated spam attempts with related data
$recent_attempts = $wpdb->get_results($wpdb->prepare("
    SELECT s.*,
        p.post_title as form_title,
        (SELECT COUNT(*)
         FROM {$stats_table}
         WHERE ip_address = s.ip_address
         AND honeypot_triggered = 1) as attempts_count,
        (SELECT GROUP_CONCAT(DISTINCT form_id)
         FROM {$stats_table}
         WHERE ip_address = s.ip_address
         AND honeypot_triggered = 1) as targeted_forms
    FROM {$stats_table} s
    LEFT JOIN {$wpdb->posts} p ON s.form_id = p.ID
    WHERE s.honeypot_triggered = 1
    ORDER BY s.created_at DESC
    LIMIT %d OFFSET %d
", $items_per_page, $offset));
?>

<!-- Main wrapper - isolated from other admin notices -->
<div class="cf7-honeypot-wrapper">
    <?php
    if (isset($_GET['message']) && isset($_GET['status'])) {
        $message = sanitize_text_field(urldecode($_GET['message']));
        $status = sanitize_text_field($_GET['status']);
        ?>
        <div class="notice notice-<?php echo esc_attr($status); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }
    ?>
    <div class="cf7-honeypot-stats">
        <!-- Dashboard Header -->
        <div class="stats-header">
            <h1><?php esc_html_e('Anti-Spam Protection: Statistics and Reports', 'cf7-honeypot'); ?></h1>
            <p><?php esc_html_e('Monitor spam attempts and analyze your website security with detailed insights.', 'cf7-honeypot'); ?>
            </p>
        </div>

        <!-- Statistics Overview Cards -->
        <div class="stats-overview">
            <!-- Last 24 Hours Stats -->
            <div class="stat-card">
                <h3><?php esc_html_e('Last 24 Hours', 'cf7-honeypot'); ?></h3>
                <div class="stat-number"><?php echo esc_html($last_24h); ?></div>
                <div class="stat-label"><?php esc_html_e('blocked attempts', 'cf7-honeypot'); ?></div>
            </div>

            <!-- Last 7 Days Stats -->
            <div class="stat-card">
                <h3><?php esc_html_e('Last 7 Days', 'cf7-honeypot'); ?></h3>
                <div class="stat-number"><?php echo esc_html($last_7d); ?></div>
                <div class="stat-label"><?php esc_html_e('blocked attempts', 'cf7-honeypot'); ?></div>
            </div>

            <!-- Last 30 Days Stats -->
            <div class="stat-card">
                <h3><?php esc_html_e('Last 30 Days', 'cf7-honeypot'); ?></h3>
                <div class="stat-number"><?php echo esc_html($last_30d); ?></div>
                <div class="stat-label"><?php esc_html_e('blocked attempts', 'cf7-honeypot'); ?></div>
            </div>

            <!-- Total Stats -->
            <div class="stat-card">
                <h3><?php esc_html_e('Total', 'cf7-honeypot'); ?></h3>
                <div class="stat-number"><?php echo esc_html($total_attempts); ?></div>
                <div class="stat-label"><?php esc_html_e('blocked attempts', 'cf7-honeypot'); ?></div>
            </div>
        </div>

        <!-- Log Management Section -->
        <div class="cleanup-section">
            <h2><?php esc_html_e('Log Management', 'cf7-honeypot'); ?></h2>
            <div class="cleanup-options">
                <form method="post" class="cleanup-form">
                    <?php wp_nonce_field('cf7_honeypot_cleanup', 'cleanup_nonce'); ?>
                    <button type="submit" name="cleanup_period" value="1" class="cleanup-button">
                        <?php esc_html_e('Clear Last 24 Hours', 'cf7-honeypot'); ?>
                    </button>
                    <button type="submit" name="cleanup_period" value="7" class="cleanup-button">
                        <?php esc_html_e('Clear Last 7 Days', 'cf7-honeypot'); ?>
                    </button>
                    <button type="submit" name="cleanup_period" value="30" class="cleanup-button">
                        <?php esc_html_e('Clear Last 30 Days', 'cf7-honeypot'); ?>
                    </button>
                    <button type="submit" name="cleanup_period" value="all" class="cleanup-button danger">
                        <?php esc_html_e('Clear All Logs', 'cf7-honeypot'); ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Recent Attempts Table -->
        <div class="attempts-table">
            <h2><?php esc_html_e('Recent Spam Attempts', 'cf7-honeypot'); ?></h2>

            <?php if (!empty($recent_attempts)): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column column-cb check-column">
                                <div class="checkbox-with-label">
                                    <input type="checkbox" id="cb-select-all-1">
                                    <span class="select-all-label"><?php esc_html_e('Select All', 'cf7-honeypot'); ?></span>
                                </div>
                            </th>
                            <th scope="col" style="width: 12%;"><?php esc_html_e('Date/Time', 'cf7-honeypot'); ?></th>
                            <th scope="col" style="width: 15%;"><?php esc_html_e('Form', 'cf7-honeypot'); ?></th>
                            <th scope="col" style="width: 12%;"><?php esc_html_e('IP Address', 'cf7-honeypot'); ?></th>
                            <th scope="col" style="width: 15%;"><?php esc_html_e('Email', 'cf7-honeypot'); ?></th>
                            <th scope="col" style="width: 12%;"><?php esc_html_e('Triggered Field', 'cf7-honeypot'); ?></th>
                            <th scope="col" style="width: 10%;"><?php esc_html_e('Risk Level', 'cf7-honeypot'); ?></th>
                            <th scope="col" style="width: 21%;"><?php esc_html_e('Actions', 'cf7-honeypot'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_attempts as $attempt): ?>
                            <!-- Main attempt row -->
                            <tr data-record-id="<?php echo esc_attr($attempt->id); ?>">
                                <td class="column-cb check-column">
                                    <input type="checkbox" name="bulk-delete[]" value="<?php echo esc_attr($attempt->id); ?>">
                                    </td>
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
                            <!-- Details row (hidden by default) -->
                            <tr class="details-row hidden">
                                <td colspan="8">
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

                <!-- Bulk Actions -->
                <div class="tablenav bottom">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-bottom" class="screen-reader-text">
                            <?php esc_html_e('Select bulk action', 'cf7-honeypot'); ?>
                        </label>
                        <select name="action" id="bulk-action-selector-bottom">
                            <option value="-1"><?php esc_html_e('Bulk Actions', 'cf7-honeypot'); ?></option>
                            <option value="delete"><?php esc_html_e('Delete', 'cf7-honeypot'); ?></option>
                        </select>
                        <button type="submit" id="doaction" class="button action"
                            onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete the selected items?', 'cf7-honeypot'); ?>')">
                            <?php esc_html_e('Apply', 'cf7-honeypot'); ?>
                        </button>
                    </div>

                    <!-- Pagination Controls -->
                    <div class="tablenav bottom">
                        <!-- Items per page selector -->
                        <div class="alignleft actions">
                            <select onchange="window.location.href='?page=cf7-honeypot-stats&per_page=' + this.value">
                                <?php foreach ([10, 20, 50, 100] as $per_page): ?>
                                    <option value="<?php echo $per_page; ?>" <?php selected($items_per_page, $per_page); ?>>
                                        <?php printf(__('%d per page', 'cf7-honeypot'), $per_page); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Pagination links -->
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php printf(
                                    _n('%s item', '%s items', $total_items, 'cf7-honeypot'),
                                    number_format_i18n($total_items)
                                ); ?>
                            </span>

                            <?php if ($total_pages > 1): ?>
                                <span class="pagination-links">
                                    <?php
                                    echo paginate_links(array(
                                        'base' => add_query_arg('paged', '%#%'),
                                        'format' => '',
                                        'prev_text' => __('&laquo;'),
                                        'next_text' => __('&raquo;'),
                                        'total' => $total_pages,
                                        'current' => $current_page,
                                        'type' => 'plain'
                                    ));
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="no-attempts"><?php esc_html_e('No spam attempts detected so far. 🎉', 'cf7-honeypot'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        wp_localize_script('cf7-honeypot-admin', 'cf7HoneypotAdmin', array(
            'deleteNonce' => wp_create_nonce('cf7_honeypot_delete_records')
        ));
        ?>
    </div>

    <!-- JavaScript for toggling details -->
    <script type="text/javascript">
        jQuery(document).ready(function ($) {
            // Initialize detail toggles
            $('.show-details').on('click', function () {
                $(this).closest('tr').next('.details-row').toggleClass('hidden');
            });

            // Initialize any tooltips
            if (typeof tippy !== 'undefined') {
                tippy('[data-tippy-content]');
            }
        });
    </script>
